<?php

/**
 * Configuration loader for llm-eval.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Console;

use Aysnc\AI\LlmEval\Cache\CachingProvider;
use Aysnc\AI\LlmEval\Cache\FilesystemCache;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use RuntimeException;

/**
 * Loads and holds configuration from llm-eval.php.
 *
 * Configuration options:
 *   - provider: Default ProviderInterface to use
 *   - directory: Directory containing eval files (default: 'evals')
 *   - cache: Cache directory path (optional, wraps provider with caching)
 *   - parallel: Whether to run in parallel by default (default: false)
 *   - concurrency: Max concurrent requests when parallel (default: 0 = unlimited)
 */
class Config
{
    private const string CONFIG_FILE = 'llm-eval.php';

    private ?ProviderInterface $provider = null;
    private string $directory = 'evals';
    private ?string $cache = null;
    private bool $parallel = false;
    private int $concurrency = 0;

    /**
     * Load configuration from a directory.
     *
     * @param string $workingDir The directory to look for llm-eval.php in.
     */
    public static function load(string $workingDir): self
    {
        $config = new self();
        $configFile = rtrim($workingDir, '/') . '/' . self::CONFIG_FILE;

        if (!file_exists($configFile)) {
            return $config;
        }

        $data = require $configFile;

        if (!is_array($data)) {
            throw new RuntimeException('llm-eval.php must return an array');
        }

        /** @var array<string, mixed> $data */
        $config->parseConfig($data, $workingDir);

        return $config;
    }

    /**
     * Check if a config file exists in the given directory.
     */
    public static function exists(string $workingDir): bool
    {
        return file_exists(rtrim($workingDir, '/') . '/' . self::CONFIG_FILE);
    }

    /**
     * Parse the configuration array.
     *
     * @param array<string, mixed> $data
     */
    private function parseConfig(array $data, string $workingDir): void
    {
        if (isset($data['provider']) && $data['provider'] instanceof ProviderInterface) {
            $this->provider = $data['provider'];
        }

        if (isset($data['directory']) && is_string($data['directory'])) {
            // Support relative paths
            $this->directory = str_starts_with($data['directory'], '/')
                ? $data['directory']
                : rtrim($workingDir, '/') . '/' . $data['directory'];
        } else {
            $this->directory = rtrim($workingDir, '/') . '/evals';
        }

        if (isset($data['cache']) && is_string($data['cache'])) {
            $this->cache = str_starts_with($data['cache'], '/')
                ? $data['cache']
                : rtrim($workingDir, '/') . '/' . $data['cache'];
        }

        if (isset($data['parallel']) && is_bool($data['parallel'])) {
            $this->parallel = $data['parallel'];
        }

        if (isset($data['concurrency']) && is_int($data['concurrency'])) {
            $this->concurrency = $data['concurrency'];
        }

        // Wrap provider with caching if cache path is set
        if ($this->provider !== null && $this->cache !== null) {
            $this->provider = new CachingProvider(
                $this->provider,
                new FilesystemCache($this->cache)
            );
        }
    }

    /**
     * Get the default provider (may be wrapped with caching).
     */
    public function getProvider(): ?ProviderInterface
    {
        return $this->provider;
    }

    /**
     * Get the evals directory path.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Get the cache directory path.
     */
    public function getCachePath(): ?string
    {
        return $this->cache;
    }

    /**
     * Whether to run in parallel by default.
     */
    public function isParallel(): bool
    {
        return $this->parallel;
    }

    /**
     * Get the concurrency limit.
     */
    public function getConcurrency(): int
    {
        return $this->concurrency;
    }

    /**
     * Discover all eval files in the configured directory.
     *
     * @return array<string> Array of file paths.
     */
    public function discoverEvalFiles(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = glob($this->directory . '/*.php');

        return is_array($files) ? $files : [];
    }
}
