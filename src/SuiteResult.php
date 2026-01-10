<?php

/**
 * Aggregated results from running a dataset.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval;

/**
 * Immutable value object containing results from multiple evaluations.
 */
readonly class SuiteResult
{
    /**
     * @param string $name The suite/evaluation name.
     * @param array<Result> $results Individual results for each test case.
     * @param bool $passed Whether all evaluations passed.
     * @param float $passRate The percentage of evaluations that passed (0.0 to 1.0).
     */
    public function __construct(
        public string $name,
        public array $results,
        public bool $passed,
        public float $passRate,
    ) {
    }

    /**
     * Create a SuiteResult from an array of individual Results.
     *
     * @param string $name The suite name.
     * @param array<Result> $results The individual results.
     */
    public static function fromResults(string $name, array $results): self
    {
        $total = count($results);
        $passedCount = 0;

        foreach ($results as $result) {
            if ($result->passed) {
                $passedCount++;
            }
        }

        $passRate = $total > 0 ? $passedCount / $total : 0.0;
        $allPassed = $passedCount === $total;

        return new self($name, $results, $allPassed, $passRate);
    }

    /**
     * Get only the failed results.
     *
     * @return array<Result>
     */
    public function getFailures(): array
    {
        return array_filter(
            $this->results,
            static fn (Result $r): bool => !$r->passed,
        );
    }

    /**
     * Get the count of passed evaluations.
     */
    public function passedCount(): int
    {
        return count($this->results) - count($this->getFailures());
    }

    /**
     * Get the count of failed evaluations.
     */
    public function failedCount(): int
    {
        return count($this->getFailures());
    }

    /**
     * Get the total count of evaluations.
     */
    public function totalCount(): int
    {
        return count($this->results);
    }

    /**
     * Get the pass rate as a percentage string.
     */
    public function passRatePercent(): string
    {
        return sprintf('%.1f%%', $this->passRate * 100);
    }
}
