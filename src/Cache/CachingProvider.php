<?php

/**
 * Caching decorator for LLM providers.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Cache;

use Aysnc\AI\LlmEval\Providers\AsyncProviderInterface;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Decorator that adds caching to any provider.
 *
 * This is invaluable during development - you can run the same evaluation
 * repeatedly without hitting the API or incurring costs. It also makes
 * your tests deterministic.
 *
 * Usage:
 *   $cache = new FilesystemCache('./.llm-cache');
 *   $provider = new CachingProvider($anthropic, $cache);
 *
 *   // First call hits the API
 *   $response = $provider->complete('What is 2+2?');
 *
 *   // Second call returns cached response instantly
 *   $response = $provider->complete('What is 2+2?');
 */
class CachingProvider implements AsyncProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function complete(string $prompt, array $options = []): Response
    {
        $key = $this->cache->generateKey($prompt, $options);

        // Check cache first
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Call the underlying provider
        $response = $this->provider->complete($prompt, $options);

        // Cache the response
        $this->cache->set($key, $response);

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function completeAsync(string $prompt, array $options = []): PromiseInterface
    {
        $key = $this->cache->generateKey($prompt, $options);

        // Check cache first
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return new FulfilledPromise($cached);
        }

        // If underlying provider supports async, use it
        if ($this->provider instanceof AsyncProviderInterface) {
            return $this->provider->completeAsync($prompt, $options)
                ->then(function (Response $response) use ($key): Response {
                    $this->cache->set($key, $response);

                    return $response;
                });
        }

        // Fall back to sync call wrapped in a promise
        $response = $this->provider->complete($prompt, $options);
        $this->cache->set($key, $response);

        return new FulfilledPromise($response);
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * Get the underlying provider.
     */
    public function getInnerProvider(): ProviderInterface
    {
        return $this->provider;
    }

    /**
     * Get the cache instance.
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}
