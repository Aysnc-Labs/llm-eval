<?php

/**
 * Expectation builder for fluent assertion API.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval;

use Aysnc\AI\LlmEval\Assertions\AssertionInterface;
use Aysnc\AI\LlmEval\Assertions\CalledTool;
use Aysnc\AI\LlmEval\Assertions\CalledToolCount;
use Aysnc\AI\LlmEval\Assertions\Contains;
use Aysnc\AI\LlmEval\Assertions\ConversationContains;
use Aysnc\AI\LlmEval\Assertions\DidNotCallTool;
use Aysnc\AI\LlmEval\Assertions\IsJson;
use Aysnc\AI\LlmEval\Assertions\JudgedBy;
use Aysnc\AI\LlmEval\Assertions\MatchesRegex;
use Aysnc\AI\LlmEval\Assertions\MaxLength;
use Aysnc\AI\LlmEval\Assertions\MinLength;
use Aysnc\AI\LlmEval\Assertions\NotContains;
use Aysnc\AI\LlmEval\Assertions\ToolCallHasParam;
use Aysnc\AI\LlmEval\Assertions\TurnCount;
use Aysnc\AI\LlmEval\Assertions\UsedTool;
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
     *
     * If $needle is null, the assertion is skipped (useful with nullable getExpected()).
     */
    public function contains(?string $needle, bool $caseSensitive = true): self
    {
        if ($needle === null) {
            return $this;
        }

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
     * Assert that a specific tool was called.
     *
     * @param string $name The tool name to check for.
     * @param int|null $times If set, assert exact number of times called.
     */
    public function calledTool(string $name, ?int $times = null): self
    {
        $this->assertions[] = new CalledTool($name, $times);

        return $this;
    }

    /**
     * Assert that a specific tool was NOT called.
     *
     * @param string $name The tool name that should not have been called.
     */
    public function didNotCallTool(string $name): self
    {
        $this->assertions[] = new DidNotCallTool($name);

        return $this;
    }

    /**
     * Assert the total number of tool calls in the response.
     *
     * @param int $count The expected total number of tool calls.
     */
    public function calledToolCount(int $count): self
    {
        $this->assertions[] = new CalledToolCount($count);

        return $this;
    }

    /**
     * Assert that a tool call has a specific parameter.
     *
     * @param string $toolName The tool name to check.
     * @param string $paramName The parameter name to check for.
     * @param mixed $value If provided, also check the parameter equals this value.
     */
    public function toolCallHasParam(string $toolName, string $paramName, mixed $value = null): self
    {
        // We use a special sentinel to distinguish "no value check" from "check for null"
        if (func_num_args() === 2) {
            $this->assertions[] = new ToolCallHasParam($toolName, $paramName);
        } else {
            $this->assertions[] = new ToolCallHasParam($toolName, $paramName, $value);
        }

        return $this;
    }

    /**
     * Assert the number of LLM calls (turns) in a conversation.
     *
     * Only works with ConversationEval. Throws LogicException if used
     * with a regular LlmEval (same pattern as calledTool).
     */
    public function turnCount(int $expected): self
    {
        $this->assertions[] = new TurnCount($expected);

        return $this;
    }

    /**
     * Assert that a tool was used in any turn of a conversation.
     *
     * Unlike calledTool() which checks the final response, this checks
     * across ALL responses in the conversation.
     *
     * Only works with ConversationEval.
     */
    public function usedTool(string $toolName): self
    {
        $this->assertions[] = new UsedTool($toolName);

        return $this;
    }

    /**
     * Assert that text appears in any message of the conversation.
     *
     * Checks all messages (user and assistant) for the needle.
     *
     * Only works with ConversationEval.
     */
    public function conversationContains(string $needle): self
    {
        $this->assertions[] = new ConversationContains($needle);

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
