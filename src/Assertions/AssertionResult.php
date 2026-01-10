<?php

/**
 * Result of a single assertion check.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

/**
 * Immutable value object representing the result of an assertion.
 */
readonly class AssertionResult
{
    /**
     * @param bool $passed Whether the assertion passed.
     * @param string $description Human-readable description of what was checked.
     * @param string $message Details about the result (especially useful for failures).
     */
    public function __construct(
        public bool $passed,
        public string $description,
        public string $message = '',
    ) {
    }

    /**
     * Create a passing result.
     */
    public static function pass(string $description): self
    {
        return new self(true, $description, 'Passed');
    }

    /**
     * Create a failing result.
     */
    public static function fail(string $description, string $message): self
    {
        return new self(false, $description, $message);
    }
}
