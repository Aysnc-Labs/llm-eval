<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Cache;

use Aysnc\AI\LlmEval\Cache\FilesystemCache;
use Aysnc\AI\LlmEval\Providers\Response;
use PHPUnit\Framework\TestCase;

class FilesystemCacheTest extends TestCase
{
    private string $cacheDir;
    private FilesystemCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/llm-eval-cache-test-' . uniqid();
        $this->cache = new FilesystemCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testSetAndGet(): void
    {
        $response = new Response(
            text: 'Test response',
            model: 'test-model',
            inputTokens: 10,
            outputTokens: 20,
        );

        $key = 'test-key';
        $this->cache->set($key, $response);

        $cached = $this->cache->get($key);

        $this->assertNotNull($cached);
        $this->assertSame('Test response', $cached->text);
        $this->assertSame('test-model', $cached->model);
        $this->assertSame(10, $cached->inputTokens);
        $this->assertSame(20, $cached->outputTokens);
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->cache->get('non-existent-key'));
    }

    public function testHas(): void
    {
        $response = new Response(text: 'test', model: 'test');

        $this->assertFalse($this->cache->has('key'));

        $this->cache->set('key', $response);

        $this->assertTrue($this->cache->has('key'));
    }

    public function testDelete(): void
    {
        $response = new Response(text: 'test', model: 'test');

        $this->cache->set('key', $response);
        $this->assertTrue($this->cache->has('key'));

        $this->cache->delete('key');

        $this->assertFalse($this->cache->has('key'));
    }

    public function testClear(): void
    {
        $response = new Response(text: 'test', model: 'test');

        $this->cache->set('key1', $response);
        $this->cache->set('key2', $response);

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        $this->cache->clear();

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGenerateKeyIsDeterministic(): void
    {
        $key1 = $this->cache->generateKey('What is 2+2?', ['model' => 'claude']);
        $key2 = $this->cache->generateKey('What is 2+2?', ['model' => 'claude']);

        $this->assertSame($key1, $key2);
    }

    public function testGenerateKeyDiffersByPrompt(): void
    {
        $key1 = $this->cache->generateKey('What is 2+2?', []);
        $key2 = $this->cache->generateKey('What is 3+3?', []);

        $this->assertNotSame($key1, $key2);
    }

    public function testGenerateKeyDiffersByOptions(): void
    {
        $key1 = $this->cache->generateKey('prompt', ['model' => 'claude-3']);
        $key2 = $this->cache->generateKey('prompt', ['model' => 'claude-4']);

        $this->assertNotSame($key1, $key2);
    }

    public function testTtlExpiration(): void
    {
        $cacheDir = sys_get_temp_dir() . '/llm-eval-ttl-test-' . uniqid();
        $cache = new FilesystemCache($cacheDir, ttl: 1);

        $response = new Response(text: 'test', model: 'test');
        $cache->set('key', $response);

        $this->assertTrue($cache->has('key'));

        // Sleep for TTL to expire
        sleep(2);

        $this->assertFalse($cache->has('key'));

        // Clean up
        $cache->clear();
        rmdir($cacheDir);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        $dir = sys_get_temp_dir() . '/llm-eval-new-dir-' . uniqid();

        $this->assertDirectoryDoesNotExist($dir);

        $cache = new FilesystemCache($dir);

        $this->assertDirectoryExists($dir);

        // Clean up
        rmdir($dir);
    }
}
