<?php

/**
 * Filesystem-based cache implementation.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Cache;

use Aysnc\AI\LlmEval\Providers\Response;
use RuntimeException;

/**
 * Simple filesystem cache for LLM responses.
 *
 * Stores responses as JSON files in a directory. Great for development
 * and CI environments where you want predictable, reproducible results.
 *
 * Usage:
 *   $cache = new FilesystemCache('/path/to/cache');
 *   $provider = new CachingProvider($anthropic, $cache);
 */
class FilesystemCache implements CacheInterface
{
    /**
     * @param string $directory The directory to store cache files.
     * @param int $ttl Time-to-live in seconds (0 = no expiration).
     */
    public function __construct(
        private readonly string $directory,
        private readonly int $ttl = 0,
    ) {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0755, true)) {
                throw new RuntimeException("Could not create cache directory: {$this->directory}");
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): ?Response
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return null;
        }

        // Check TTL
        if ($this->ttl > 0) {
            $modified = filemtime($path);
            if ($modified !== false && (time() - $modified) > $this->ttl) {
                $this->delete($key);

                return null;
            }
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $this->deserializeResponse($data);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, Response $response): void
    {
        $path = $this->getPath($key);
        $data = $this->serializeResponse($response);
        $json = json_encode($data, JSON_PRETTY_PRINT);

        if ($json === false) {
            throw new RuntimeException('Failed to serialize response to JSON');
        }

        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException("Failed to write cache file: {$path}");
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        $path = $this->getPath($key);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $files = glob($this->directory . '/*.json');

        if (is_array($files)) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function generateKey(string $prompt, array $options): string
    {
        // Create a deterministic key from prompt and options
        $data = [
            'prompt' => $prompt,
            'options' => $options,
        ];

        return hash('sha256', (string) json_encode($data));
    }

    /**
     * Get the file path for a cache key.
     */
    private function getPath(string $key): string
    {
        return $this->directory . '/' . $key . '.json';
    }

    /**
     * Serialize a Response to an array.
     *
     * @return array<string, mixed>
     */
    private function serializeResponse(Response $response): array
    {
        return [
            'text' => $response->text,
            'model' => $response->model,
            'inputTokens' => $response->inputTokens,
            'outputTokens' => $response->outputTokens,
            'raw' => $response->raw,
            '_cached_at' => time(),
        ];
    }

    /**
     * Deserialize an array to a Response.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeResponse(array $data): ?Response
    {
        if (!is_string($data['text'] ?? null) || !is_string($data['model'] ?? null)) {
            return null;
        }

        $raw = is_array($data['raw'] ?? null) ? $data['raw'] : [];

        return new Response(
            text: $data['text'],
            model: $data['model'],
            inputTokens: is_int($data['inputTokens'] ?? null) ? $data['inputTokens'] : 0,
            outputTokens: is_int($data['outputTokens'] ?? null) ? $data['outputTokens'] : 0,
            raw: $this->ensureStringKeys($raw),
        );
    }

    /**
     * Ensure array has string keys for PHPStan.
     *
     * @param array<mixed, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function ensureStringKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
