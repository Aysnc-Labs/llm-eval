<?php

/**
 * Evaluation result.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval;

use Aysnc\AI\LlmEval\Assertions\AssertionResult;
use Aysnc\AI\LlmEval\Providers\Response;

/**
 * Immutable value object representing the result of an evaluation.
 *
 * Contains the LLM response, all assertion results, and overall pass/fail status.
 */
readonly class Result
{
    /**
     * @param string $name The evaluation name.
     * @param Response $response The LLM response.
     * @param array<AssertionResult> $assertionResults Results of each assertion.
     * @param bool $passed Whether all assertions passed.
     */
    public function __construct(
        public string $name,
        public Response $response,
        public array $assertionResults,
        public bool $passed,
    ) {
    }

    /**
     * Create a result from a response and assertion results.
     *
     * @param string $name The evaluation name.
     * @param Response $response The LLM response.
     * @param array<AssertionResult> $assertionResults Results of each assertion.
     */
    public static function fromAssertions(
        string $name,
        Response $response,
        array $assertionResults,
    ): self {
        $passed = true;

        foreach ($assertionResults as $result) {
            if (!$result->passed) {
                $passed = false;
                break;
            }
        }

        return new self($name, $response, $assertionResults, $passed);
    }

    /**
     * Get only the failed assertion results.
     *
     * @return array<AssertionResult>
     */
    public function getFailures(): array
    {
        return array_filter(
            $this->assertionResults,
            static fn (AssertionResult $r): bool => !$r->passed,
        );
    }

    /**
     * Get the count of passed assertions.
     */
    public function passedCount(): int
    {
        return count($this->assertionResults) - count($this->getFailures());
    }

    /**
     * Get the count of failed assertions.
     */
    public function failedCount(): int
    {
        return count($this->getFailures());
    }

    /**
     * Get the total count of assertions.
     */
    public function totalCount(): int
    {
        return count($this->assertionResults);
    }
}
