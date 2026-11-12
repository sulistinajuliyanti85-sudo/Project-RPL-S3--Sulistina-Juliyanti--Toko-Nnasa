<?php
/**
 * Order & Transaction Intelligence Service
 * Store: nnasa_ca Accessories Marketplace
 * Provides safe order lookups, user order history summaries, and admin financial aggregations
 */

class OrderIntelligenceService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Look up order by order number or ID
     */
    public function lookupOrder(string $orderNumber, ?int $userId = null, bool $isAdmin = false): ?array {
        $cleanNumber = trim($orderNumber);
        $sql = "
            SELECT o.*, u.name AS customer_name, u.email AS customer_email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.order_number = ?
        ";
        $params = [$cleanNumber];

        // Non-admin can only look up their own order
        if (!$isAdmin && $userId !== null) {
            $sql .= " AND o.user_id = ?";
            $params[] = $userId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) return null;

        // Fetch order items
        $iStmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $iStmt->execute([$order['id']]);
        $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch shipment info
        $sStmt = $this->db->prepare("SELECT * FROM shipments WHERE order_id = ? LIMIT 1");
        $sStmt->execute([$order['id']]);
        $order['shipment'] = $sStmt->fetch(PDO::FETCH_ASSOC);

        return $order;
    }

    /**
     * Get customer personal orders summary
     */
    public function getUserOrdersSummary(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT order_status, COUNT(*) AS total
            FROM orders
            WHERE user_id = ?
            GROUP BY order_status
        ");
        $stmt->execute([$userId]);
        $statuses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $count = (int)$countStmt->fetchColumn();

        // Latest order
        $latestStmt = $this->db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $latestStmt->execute([$userId]);
        $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_orders' => $count,
            'by_status' => $statuses,
            'latest_order' => $latest
        ];
    }

    /**
     * Get platform revenue and sales summary (Admin role only!)
     */
    public function getAdminRevenueSummary(): array {
        $totalValidRevenue = (float)$this->db->query("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM orders
            WHERE payment_status = 'paid' AND order_status != 'cancelled'
        ")->fetchColumn();

        $totalOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $pendingOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
        $processingOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'processing'")->fetchColumn();
        $shippedOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'shipped'")->fetchColumn();
        $completedOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'completed'")->fetchColumn();
        $cancelledOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'cancelled'")->fetchColumn();

        $todayRevenue = (float)$this->db->query("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM orders
            WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()
        ")->fetchColumn();

        return [
            'total_revenue' => $totalValidRevenue,
            'total_revenue_formatted' => function_exists('format_rupiah') ? format_rupiah($totalValidRevenue) : 'Rp ' . number_format($totalValidRevenue, 0, ',', '.'),
            'today_revenue' => $todayRevenue,
            'today_revenue_formatted' => function_exists('format_rupiah') ? format_rupiah($todayRevenue) : 'Rp ' . number_format($todayRevenue, 0, ',', '.'),
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'processing_orders' => $processingOrders,
            'shipped_orders' => $shippedOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders
        ];
    }
}
