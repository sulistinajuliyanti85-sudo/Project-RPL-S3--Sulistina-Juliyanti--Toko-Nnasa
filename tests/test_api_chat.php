<?php
/**
 * Test HTTP API endpoint for AI Chatbot
 */

$ch = curl_init('http://localhost/toko/api/ai-chat.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => 'Halo kak, ada kalung clover?']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
$data = json_decode($res, true);
echo "JSON Valid: " . (is_array($data) ? "YES" : "NO") . "\n";
echo "Success Field: " . (!empty($data['success']) ? "YES" : "NO") . "\n";
echo "Reply Preview: " . substr($data['reply'] ?? '', 0, 75) . "...\n";
echo "Products Count: " . count($data['products'] ?? []) . "\n";
echo "Suggestions Count: " . count($data['suggestions'] ?? []) . "\n";
