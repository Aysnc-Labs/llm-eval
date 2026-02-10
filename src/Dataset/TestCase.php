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
 * Access data via:
 *   $testCase->getPrompt()         — the prompt string
 *   $testCase->getExpected()       — default expected value (?string)
 *   $testCase->getExpected('name') — named expected value (?string)
 *   $testCase->getData()           — all data as array (prompt, expected, metadata merged)
 *   $testCase->getData('criteria') — single value from any key (mixed)
 */
class TestCase
{
    private ?int $turn = null;

    /**
     * @param string $prompt The prompt to send to the LLM.
     * @param array<string, string> $expected Expected values for assertions.
     * @param array<string, mixed> $metadata Additional metadata about this test case.
     */
    public function __construct(
        private readonly string $prompt,
        private readonly array $expected = [],
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Get the prompt to send to the LLM.
     */
    public function getPrompt(): string
    {
        return $this->prompt;
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
     * Get the 1-indexed turn number in a conversation (null for non-conversation evals).
     */
    public function getTurn(): ?int
    {
        return $this->turn;
    }

    /**
     * Return a new instance with the turn number set.
     */
    public function withTurn(int $turn): self
    {
        $clone = clone $this;
        $clone->turn = $turn;

        return $clone;
    }

    /**
     * Get all data as a merged array, or a single value by key.
     *
     * Returns prompt, expected, and metadata merged into a flat structure.
     * The 'expected' key contains the expected values array.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function getData(?string $key = null): mixed
    {
        $data = [...$this->metadata, 'prompt' => $this->prompt, 'expected' => $this->expected];

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? null;
    }

    /**
     * Create from an associative array.
     *
     * The 'prompt' key is required. The 'expected' key can be:
     *   - A string: maps to ['default' => $value]
     *   - An array: used directly as expected values
     *
     * Keys starting with 'expected_' also become expected values (for CSV compat).
     * Everything else becomes metadata.
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
            } elseif ($key === 'expected') {
                if (is_string($value)) {
                    $expected['default'] = $value;
                } elseif (is_array($value)) {
                    /** @var array<string, string> $value */
                    $expected = $value;
                }
            } elseif (str_starts_with($key, 'expected_')) {
                $expectedKey = substr($key, 9); // Remove 'expected_' prefix
                $expected[$expectedKey] = is_string($value) ? $value : '';
            } else {
                $metadata[$key] = $value;
            }
        }

        return new self($prompt, $expected, $metadata);
    }
}
