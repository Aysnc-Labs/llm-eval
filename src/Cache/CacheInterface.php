<?php

/**
 * Cache interface for LLM responses.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Cache;

use Aysnc\AI\LlmEval\Providers\Response;

/**
 * Interface for caching LLM responses.
 *
 * Implementations can use any storage backend (filesystem, Redis, etc.).
 */
interface CacheInterface
{
    /**
     * Get a cached response.
     *
     * @param string $key The cache key.
     *
     * @return Response|null The cached response, or null if not found.
     */
    public function get(string $key): ?Response;

    /**
     * Store a response in the cache.
     *
     * @param string $key The cache key.
     * @param Response $response The response to cache.
     */
    public function set(string $key, Response $response): void;

    /**
     * Check if a key exists in the cache.
     *
     * @param string $key The cache key.
     */
    public function has(string $key): bool;

    /**
     * Delete a cached item.
     *
     * @param string $key The cache key.
     */
    public function delete(string $key): void;

    /**
     * Clear all cached items.
     */
    public function clear(): void;

    /**
     * Generate a cache key from prompt and options.
     *
     * @param string $prompt The prompt.
     * @param array<string, mixed> $options The provider options.
     */
    public function generateKey(string $prompt, array $options): string;
}
