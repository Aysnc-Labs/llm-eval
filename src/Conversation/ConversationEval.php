<?php

/**
 * Evaluation runner for multi-turn conversations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Conversation;

use Aysnc\AI\LlmEval\Assertions\ConversationAwareAssertion;
use Aysnc\AI\LlmEval\Assertions\JudgedBy;
use Aysnc\AI\LlmEval\Assertions\ResponseAwareAssertion;
use Aysnc\AI\LlmEval\Dataset\TestCase;
use Aysnc\AI\LlmEval\Expectation;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolExecutorInterface;
use Aysnc\AI\LlmEval\Result;
use Aysnc\AI\LlmEval\SuiteResult;
use Closure;
use InvalidArgumentException;
use Override;

/**
 * Runs evaluations against multi-turn conversations with tool execution.
 *
 * Each dataset row represents a conversation. The row's `prompt` is the first
 * user message. Optional `replies` in metadata define follow-up turns — each
 * can be a string (just a prompt) or an array with `prompt` and `expected_*`
 * keys for per-turn assertions.
 *
 * Every turn (initial + each reply) produces its own Result in the suite,
 * so each step of the conversation is independently testable.
 *
 * Usage:
 *   $result = LlmEval::createConversation('tool-test')
 *       ->provider($provider)
 *       ->executor($executor)
 *       ->withTools($tools)
 *       ->dataset($dataset)
 *       ->assertions(function ($expect, $testCase) {
 *           $expected = $testCase->getExpected('default');
 *           if ($expected !== null) {
 *               $expect->contains($expected);
 *           }
 *           $expect->usedTool('get_weather');
 *       })
 *       ->runAll();
 */
class ConversationEval extends LlmEval
{
    protected ?ToolExecutorInterface $executor = null;

    /** @var array<array<string, mixed>> */
    protected array $tools = [];

    protected int $maxTurns = 10;

    /**
     * @param string $name A descriptive name for this evaluation.
     */
    #[Override]
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the tool executor for the conversation.
     */
    public function executor(ToolExecutorInterface $executor): self
    {
        $this->executor = $executor;

        return $this;
    }

    /**
     * Set the tool definitions to send to the LLM.
     *
     * @param array<array<string, mixed>> $tools Tool definitions in Anthropic format.
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Set the maximum number of tool-loop turns per conversation.
     */
    public function withMaxTurns(int $maxTurns): self
    {
        $this->maxTurns = $maxTurns;

        return $this;
    }

    #[Override]
    public function runAll(): SuiteResult
    {
        if ($this->dataset === null) {
            throw new InvalidArgumentException('Dataset must be set before running runAll()');
        }

        $provider = $this->provider;

        if ($provider === null) {
            throw new InvalidArgumentException('Provider must be set before running evaluation');
        }

        if (! $provider instanceof ConversableProviderInterface) {
            throw new InvalidArgumentException(
                'Provider must implement ConversableProviderInterface for conversation evaluations.'
            );
        }

        $executor = $this->executor;

        if ($executor === null) {
            throw new InvalidArgumentException('Executor must be set via executor() before running conversation evaluations');
        }

        if ($this->assertionBuilder === null) {
            throw new InvalidArgumentException('Assertion builder must be set via assertions() before running runAll()');
        }

        $assertionBuilder = $this->assertionBuilder;
        $results = [];

        foreach ($this->dataset as $index => $testCase) {
            $conversation = Conversation::make($provider, $executor)
                ->withTools($this->tools)
                ->withMaxTurns($this->maxTurns);

            $maxTokens = $this->options['max_tokens'] ?? null;
            if (is_int($maxTokens)) {
                $conversation->withMaxTokens($maxTokens);
            }

            $caseName = is_string($testCase->metadata['name'] ?? null)
                ? $testCase->metadata['name']
                : "Case {$index}";

            // Build turns: initial prompt + replies.
            $turns = $this->buildTurns($testCase);

            foreach ($turns as $turnIndex => $turnTestCase) {
                if ($turnIndex === 0) {
                    $response = $conversation->send($turnTestCase->prompt);
                } else {
                    $response = $conversation->reply($turnTestCase->prompt);
                }

                $results[] = $this->buildTurnResult(
                    $assertionBuilder,
                    $turnTestCase,
                    $response,
                    $conversation,
                    $caseName,
                    (int) $turnIndex + 1,
                    count($turns),
                );
            }
        }

        return SuiteResult::fromResults($this->name, $results);
    }

    /**
     * Build a list of TestCase objects for each turn in the conversation.
     *
     * The initial prompt becomes the first turn. Each reply becomes a subsequent
     * turn. Replies can be strings (just a prompt) or arrays (prompt + expected values).
     *
     * @return array<TestCase>
     */
    private function buildTurns(TestCase $testCase): array
    {
        // First turn: the original test case prompt + expected values.
        $turns = [$testCase];

        $replies = $testCase->metadata['replies'] ?? [];
        if (! is_array($replies)) {
            return $turns;
        }

        foreach ($replies as $reply) {
            if (is_string($reply)) {
                $turns[] = new TestCase($reply);
            } elseif (is_array($reply)) {
                /** @var array<string, mixed> $reply */
                $turns[] = TestCase::fromArray($reply);
            }
        }

        return $turns;
    }

    /**
     * Run assertions for a single turn and return a Result.
     *
     * @param Closure $assertionBuilder
     */
    private function buildTurnResult(
        Closure $assertionBuilder,
        TestCase $turnTestCase,
        Response $response,
        Conversation $conversation,
        string $caseName,
        int $turnNumber,
        int $totalTurns,
    ): Result {
        $expectation = new Expectation($this);
        $assertionBuilder($expectation, $turnTestCase);

        $assertionResults = [];
        foreach ($expectation->getAssertions() as $assertion) {
            if ($assertion instanceof JudgedBy) {
                $assertion = $assertion->withOriginalPrompt($turnTestCase->prompt);
            }
            if ($assertion instanceof ResponseAwareAssertion) {
                $assertion = $assertion->withResponse($response);
            }
            if ($assertion instanceof ConversationAwareAssertion) {
                $assertion = $assertion->withConversation($conversation);
            }
            $assertionResults[] = $assertion->check($response->text);
        }

        // Single-turn conversations don't need "Turn N" suffix.
        $resultName = $totalTurns > 1
            ? "{$this->name} - {$caseName} - Turn {$turnNumber}"
            : "{$this->name} - {$caseName}";

        return Result::fromAssertions($resultName, $response, $assertionResults);
    }

    /**
     * Conversation evaluations run sequentially (each row is a multi-turn loop).
     */
    #[Override]
    public function runAllParallel(int $concurrency = 0): SuiteResult
    {
        return $this->runAll();
    }
}
