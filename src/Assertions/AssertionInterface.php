<?php

/**
 * Interface for assertions.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

/**
 * Contract that all assertions must implement.
 *
 * Assertions check a condition against an LLM response text
 * and return whether it passed or failed.
 */
interface AssertionInterface
{
    /**
     * Check the assertion against the given text.
     *
     * @param string $text The LLM response text to check.
     *
     * @return AssertionResult The result of the assertion.
     */
    public function check(string $text): AssertionResult;

    /**
     * Get a human-readable description of this assertion.
     */
    public function getDescription(): string;
}
