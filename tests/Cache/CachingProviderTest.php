<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Cache;

use Aysnc\AI\LlmEval\Cache\CachingProvider;
use Aysnc\AI\LlmEval\Cache\FilesystemCache;
use Aysnc\AI\LlmEval\Providers\AsyncProviderInterface;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use GuzzleHttp\Promise\FulfilledPromise;
use PHPUnit\Framework\TestCase;

class CachingProviderTest extends TestCase
{
    private string $cacheDir;
    private FilesystemCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/llm-eval-caching-provider-test-' . uniqid();
        $this->cache = new FilesystemCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testCompleteCachesResponse(): void
    {
        $callCount = 0;
        $innerProvider = $this->createSyncProvider(function () use (&$callCount): Response {
            $callCount++;

            return new Response(text: 'response', model: 'test');
        });

        $provider = new CachingProvider($innerProvider, $this->cache);

        // First call hits the provider
        $response1 = $provider->complete('prompt');
        $this->assertSame(1, $callCount);
        $this->assertSame('response', $response1->text);

        // Second call returns cached response
        $response2 = $provider->complete('prompt');
        $this->assertSame(1, $callCount); // Still 1, didn't call provider again
        $this->assertSame('response', $response2->text);
    }

    public function testCompleteAsyncCachesResponse(): void
    {
        $callCount = 0;
        $innerProvider = $this->createAsyncProvider(function () use (&$callCount): Response {
            $callCount++;

            return new Response(text: 'async response', model: 'test');
        });

        $provider = new CachingProvider($innerProvider, $this->cache);

        // First call hits the provider
        $response1 = $provider->completeAsync('prompt')->wait();
        $this->assertSame(1, $callCount);
        $this->assertInstanceOf(Response::class, $response1);
        $this->assertSame('async response', $response1->text);

        // Second call returns cached response
        $response2 = $provider->completeAsync('prompt')->wait();
        $this->assertSame(1, $callCount); // Still 1
        $this->assertInstanceOf(Response::class, $response2);
    }

    public function testDifferentPromptsAreCachedSeparately(): void
    {
        $innerProvider = $this->createSyncProvider(function (string $prompt): Response {
            return new Response(text: "Response to: {$prompt}", model: 'test');
        });

        $provider = new CachingProvider($innerProvider, $this->cache);

        $response1 = $provider->complete('prompt1');
        $response2 = $provider->complete('prompt2');

        $this->assertSame('Response to: prompt1', $response1->text);
        $this->assertSame('Response to: prompt2', $response2->text);
    }

    public function testDifferentOptionsAreCachedSeparately(): void
    {
        $callCount = 0;
        $innerProvider = $this->createSyncProvider(function () use (&$callCount): Response {
            $callCount++;

            return new Response(text: 'response', model: 'test');
        });

        $provider = new CachingProvider($innerProvider, $this->cache);

        $provider->complete('prompt', ['model' => 'model-a']);
        $provider->complete('prompt', ['model' => 'model-b']);

        // Should have called provider twice (different options = different cache keys)
        $this->assertSame(2, $callCount);
    }

    public function testClearCache(): void
    {
        $callCount = 0;
        $innerProvider = $this->createSyncProvider(function () use (&$callCount): Response {
            $callCount++;

            return new Response(text: 'response', model: 'test');
        });

        $provider = new CachingProvider($innerProvider, $this->cache);

        $provider->complete('prompt');
        $this->assertSame(1, $callCount);

        $provider->clearCache();

        $provider->complete('prompt');
        $this->assertSame(2, $callCount); // Called again after cache cleared
    }

    public function testGetInnerProvider(): void
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $provider = new CachingProvider($innerProvider, $this->cache);

        $this->assertSame($innerProvider, $provider->getInnerProvider());
    }

    public function testGetCache(): void
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $provider = new CachingProvider($innerProvider, $this->cache);

        $this->assertSame($this->cache, $provider->getCache());
    }

    public function testCompleteAsyncWithSyncProviderFallback(): void
    {
        // Use a sync-only provider (not AsyncProviderInterface)
        $callCount = 0;
        $innerProvider = $this->createSyncProvider(function () use (&$callCount): Response {
            $callCount++;

            return new Response(text: 'sync fallback', model: 'test');
        });

        $provider = new CachingProvider($innerProvider, $this->cache);

        // completeAsync should still work, falling back to sync
        $response = $provider->completeAsync('prompt')->wait();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('sync fallback', $response->text);
        $this->assertSame(1, $callCount);
    }

    /**
     * Create a sync provider mock.
     *
     * @param callable(string): Response $callback
     */
    private function createSyncProvider(callable $callback): ProviderInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')
            ->willReturnCallback($callback);

        return $provider;
    }

    /**
     * Create an async provider mock.
     *
     * @param callable(string): Response $callback
     */
    private function createAsyncProvider(callable $callback): AsyncProviderInterface
    {
        $provider = $this->createMock(AsyncProviderInterface::class);
        $provider->method('complete')
            ->willReturnCallback($callback);
        $provider->method('completeAsync')
            ->willReturnCallback(fn (string $prompt) => new FulfilledPromise($callback($prompt)));

        return $provider;
    }
}
