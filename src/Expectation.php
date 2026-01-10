<?php

/**
 * Expectation builder for fluent assertion API.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval;

use Aysnc\AI\LlmEval\Assertions\AssertionInterface;
use Aysnc\AI\LlmEval\Assertions\Contains;
use Aysnc\AI\LlmEval\Assertions\IsJson;
use Aysnc\AI\LlmEval\Assertions\JudgedBy;
use Aysnc\AI\LlmEval\Assertions\MatchesRegex;
use Aysnc\AI\LlmEval\Assertions\MaxLength;
use Aysnc\AI\LlmEval\Assertions\MinLength;
use Aysnc\AI\LlmEval\Assertions\NotContains;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;

/**
 * Fluent builder for adding assertions to an evaluation.
 *
 * This class collects assertions and provides a chainable API.
 * It maintains a reference back to the parent LlmEval for method chaining.
 */
class Expectation
{
    /**
     * The assertions to run.
     *
     * @var array<AssertionInterface>
     */
    private array $assertions = [];

    /**
     * @param LlmEval $eval The parent evaluation (for method chaining back).
     */
    public function __construct(
        private readonly LlmEval $eval,
    ) {
    }

    /**
     * Assert that the response contains a substring.
     */
    public function contains(string $needle, bool $caseSensitive = true): self
    {
        $this->assertions[] = new Contains($needle, $caseSensitive);

        return $this;
    }

    /**
     * Assert that the response does not contain a substring.
     */
    public function notContains(string $needle, bool $caseSensitive = true): self
    {
        $this->assertions[] = new NotContains($needle, $caseSensitive);

        return $this;
    }

    /**
     * Assert that the response matches a regex pattern.
     */
    public function matchesRegex(string $pattern): self
    {
        $this->assertions[] = new MatchesRegex($pattern);

        return $this;
    }

    /**
     * Assert that the response is valid JSON.
     */
    public function isJson(): self
    {
        $this->assertions[] = new IsJson();

        return $this;
    }

    /**
     * Assert that the response does not exceed a maximum length.
     */
    public function maxLength(int $length): self
    {
        $this->assertions[] = new MaxLength($length);

        return $this;
    }

    /**
     * Assert that the response meets a minimum length.
     */
    public function minLength(int $length): self
    {
        $this->assertions[] = new MinLength($length);

        return $this;
    }

    /**
     * Add a custom assertion.
     */
    public function assert(AssertionInterface $assertion): self
    {
        $this->assertions[] = $assertion;

        return $this;
    }

    /**
     * Use an LLM to judge the response against criteria.
     *
     * @param ProviderInterface $judge The LLM provider to use as judge.
     * @param string $criteria The criteria to evaluate against (e.g., "Is this helpful?").
     * @param float $threshold Minimum score to pass (0.0 to 1.0, default 0.7).
     * @param string|null $model Optional model override for the judge.
     */
    public function judgedBy(
        ProviderInterface $judge,
        string $criteria,
        float $threshold = 0.7,
        ?string $model = null,
    ): self {
        $this->assertions[] = new JudgedBy($judge, $criteria, $threshold, $model);

        return $this;
    }

    /**
     * Run the evaluation with all configured assertions.
     */
    public function run(): Result
    {
        return $this->eval->runWithAssertions($this->assertions);
    }

    /**
     * Get all configured assertions.
     *
     * @return array<AssertionInterface>
     */
    public function getAssertions(): array
    {
        return $this->assertions;
    }
}
