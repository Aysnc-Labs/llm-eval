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
use Aysnc\AI\LlmEval\Expectation;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\ToolExecutorInterface;
use Aysnc\AI\LlmEval\Result;
use Aysnc\AI\LlmEval\SuiteResult;
use InvalidArgumentException;
use Override;

/**
 * Runs evaluations against multi-turn conversations with tool execution.
 *
 * Each dataset row is sent through a Conversation (with its tool loop),
 * and assertions run against both the final Response and the Conversation.
 *
 * Usage:
 *   $result = LlmEval::createConversation('tool-test')
 *       ->provider($provider)
 *       ->executor($executor)
 *       ->withTools($tools)
 *       ->dataset($dataset)
 *       ->assertions(function ($expect, $testCase) {
 *           $expect->contains('weather')
 *               ->turnCount(2)
 *               ->usedTool('get_weather');
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

            $response = $conversation->send($testCase->prompt);

            // Send follow-up replies if the test case has them.
            $replies = $testCase->metadata['replies'] ?? [];
            if (is_array($replies)) {
                foreach ($replies as $reply) {
                    if (is_string($reply)) {
                        $response = $conversation->reply($reply);
                    }
                }
            }

            // Build assertions for this test case.
            $expectation = new Expectation($this);
            $assertionBuilder($expectation, $testCase);

            // Apply assertions.
            $assertionResults = [];
            foreach ($expectation->getAssertions() as $assertion) {
                if ($assertion instanceof JudgedBy) {
                    $assertion = $assertion->withOriginalPrompt($testCase->prompt);
                }
                if ($assertion instanceof ResponseAwareAssertion) {
                    $assertion = $assertion->withResponse($response);
                }
                if ($assertion instanceof ConversationAwareAssertion) {
                    $assertion = $assertion->withConversation($conversation);
                }
                $assertionResults[] = $assertion->check($response->text);
            }

            $caseName = is_string($testCase->metadata['name'] ?? null)
                ? $testCase->metadata['name']
                : "Case {$index}";

            $results[] = Result::fromAssertions(
                $this->name . ' - ' . $caseName,
                $response,
                $assertionResults,
            );
        }

        return SuiteResult::fromResults($this->name, $results);
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
