<?php

/**
 * Caching decorator for LLM providers.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Cache;

use Aysnc\AI\LlmEval\Providers\AsyncConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\AsyncProviderInterface;
use Aysnc\AI\LlmEval\Providers\ConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\Message;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use RuntimeException;

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
class CachingProvider implements AsyncConversableProviderInterface
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
     * @inheritDoc
     */
    public function completeWithMessages(array $messages, array $options = []): Response
    {
        $key = $this->generateMessagesKey($messages, $options);

        // Check cache first
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        if (!$this->provider instanceof ConversableProviderInterface) {
            throw new RuntimeException(
                'Inner provider does not support completeWithMessages(). '
                . 'Wrap a ConversableProviderInterface implementation.'
            );
        }

        $response = $this->provider->completeWithMessages($messages, $options);
        $this->cache->set($key, $response);

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function completeWithMessagesAsync(array $messages, array $options = []): PromiseInterface
    {
        $key = $this->generateMessagesKey($messages, $options);

        // Check cache first
        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return new FulfilledPromise($cached);
        }

        if (!$this->provider instanceof ConversableProviderInterface) {
            throw new RuntimeException(
                'Inner provider does not support completeWithMessages(). '
                . 'Wrap a ConversableProviderInterface implementation.'
            );
        }

        // If underlying provider supports async conversations, use it
        if ($this->provider instanceof AsyncConversableProviderInterface) {
            return $this->provider->completeWithMessagesAsync($messages, $options)
                ->then(function (Response $response) use ($key): Response {
                    $this->cache->set($key, $response);

                    return $response;
                });
        }

        // Fall back to sync call wrapped in a promise
        $response = $this->provider->completeWithMessages($messages, $options);
        $this->cache->set($key, $response);

        return new FulfilledPromise($response);
    }

    /**
     * Generate a cache key from messages and options.
     *
     * @param array<Message> $messages
     * @param array<string, mixed> $options
     */
    private function generateMessagesKey(array $messages, array $options): string
    {
        $serialized = array_map(fn (Message $m) => $m->toArray(), $messages);
        $json = json_encode(['messages' => $serialized, 'options' => $options]);
        assert(is_string($json));

        return sha1($json);
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
