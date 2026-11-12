<?php
/**
 * AI Provider Adapter Interface
 * Store: nnasa_ca Accessories Marketplace
 * 
 * Provides a standardized contract for all AI LLM providers.
 */

interface AIProviderAdapterInterface {
    /**
     * Get provider unique key name (e.g. 'gemini', 'openai')
     */
    public function getProviderName(): string;

    /**
     * Test connection with given credentials or stored credentials
     */
    public function testConnection(?string $apiKey = null, ?string $baseUrl = null): array;

    /**
     * Fetch list of available models from provider API
     */
    public function fetchAvailableModels(?string $apiKey = null): array;

    /**
     * Generate content / text completion
     */
    public function generateContent(
        string $prompt,
        string $systemInstruction = '',
        string $model = '',
        array $parameters = [],
        ?string $apiKey = null
    ): array;
}
