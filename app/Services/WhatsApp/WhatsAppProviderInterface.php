<?php
/**
 * WhatsApp Provider Interface
 * Store: nnasa_ca Accessories Marketplace
 * Standard contract for WhatsApp API Gateways (Fonnte, Wablas, Meta Cloud API, Generic)
 */

interface WhatsAppProviderInterface {
    /**
     * Get unique provider identifier (e.g. 'fonnte', 'wablas', 'meta_cloud', 'generic')
     */
    public function getProviderName(): string;

    /**
     * Test connection / credentials validity with provider
     */
    public function testConnection(?string $token = null, ?string $apiUrl = null): array;

    /**
     * Send WhatsApp message to a normalized international phone number (e.g. 628123456789)
     */
    public function sendMessage(
        string $normalizedPhone,
        string $message,
        ?string $token = null,
        ?string $apiUrl = null,
        ?string $senderId = null,
        int $timeout = 12
    ): array;
}
