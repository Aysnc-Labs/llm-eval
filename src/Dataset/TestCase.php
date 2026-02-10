<?php

/**
 * A single test case from a dataset.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Dataset;

/**
 * Immutable value object representing a single test case.
 *
 * A test case contains a prompt and optional expected values
 * that can be used in assertions.
 */
readonly class TestCase
{
    /**
     * @param string $prompt The prompt to send to the LLM.
     * @param array<string, string> $expected Expected values for assertions.
     * @param array<string, mixed> $metadata Additional metadata about this test case.
     */
    public function __construct(
        public string $prompt,
        public array $expected = [],
        public array $metadata = [],
    ) {
    }

    /**
     * Get an expected value by key.
     */
    public function getExpected(string $key = 'default'): ?string
    {
        return $this->expected[$key] ?? null;
    }

    /**
     * Check if an expected value exists.
     */
    public function hasExpected(string $key = 'default'): bool
    {
        return isset($this->expected[$key]);
    }

    /**
     * Create from an associative array.
     *
     * The 'prompt' key is required. All other keys starting with 'expected_'
     * become expected values. Everything else becomes metadata.
     *
     * @param array<string, mixed> $data The data array.
     */
    public static function fromArray(array $data): self
    {
        $prompt = '';
        $expected = [];
        $metadata = [];

        foreach ($data as $key => $value) {
            if ($key === 'prompt') {
                $prompt = is_string($value) ? $value : '';
            } elseif (str_starts_with($key, 'expected_')) {
                $expectedKey = substr($key, 9); // Remove 'expected_' prefix
                $expected[$expectedKey] = is_string($value) ? $value : '';
            } elseif ($key === 'expected') {
                // Single 'expected' column maps to 'default' key
                $expected['default'] = is_string($value) ? $value : '';
            } else {
                $metadata[$key] = $value;
            }
        }

        return new self($prompt, $expected, $metadata);
    }
}
