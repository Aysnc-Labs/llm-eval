<?php

/**
 * LLM-as-judge assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Conversation\Conversation;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Override;

/**
 * Uses an LLM to judge whether a response meets a criteria.
 *
 * This enables subjective evaluations like "is this helpful?" or
 * "is the tone appropriate?" that can't be checked with simple assertions.
 */
class JudgedBy implements AssertionInterface, ConversationAwareAssertion
{
    private const string JUDGE_PROMPT = <<<'PROMPT'
        You are evaluating an LLM response against specific criteria.

        ## Original Prompt
        %s

        ## Response to Evaluate
        %s

        ## Evaluation Criteria
        %s

        ## Instructions
        Evaluate if the response meets the criteria. Consider:
        - Does the response address the criteria?
        - Rate your confidence from 0.0 to 1.0

        Respond in this exact JSON format:
        {"pass": true/false, "score": 0.0-1.0, "reasoning": "brief explanation"}
        PROMPT;

    private const string CONVERSATION_JUDGE_PROMPT = <<<'PROMPT'
        You are evaluating an LLM response against specific criteria.
        This response is part of a multi-turn conversation.

        ## Conversation History
        %s

        ## Current Prompt
        %s

        ## Response to Evaluate
        %s

        ## Evaluation Criteria
        %s

        ## Instructions
        Evaluate the response in context of the full conversation.
        Consider whether it correctly builds on previous turns.
        Rate your confidence from 0.0 to 1.0

        Respond in this exact JSON format:
        {"pass": true/false, "score": 0.0-1.0, "reasoning": "brief explanation"}
        PROMPT;

    /**
     * The original prompt (set before check() is called).
     */
    private string $originalPrompt = '';

    private ?Conversation $conversation = null;

    /**
     * @param ProviderInterface $judge The LLM provider to use as judge.
     * @param string $criteria The criteria to evaluate against.
     * @param float $threshold Minimum score to pass (0.0 to 1.0).
     * @param string|null $model Optional model override for the judge.
     */
    public function __construct(
        private readonly ProviderInterface $judge,
        private readonly string $criteria,
        private readonly float $threshold = 0.7,
        private readonly ?string $model = null,
    ) {
    }

    /**
     * Set the original prompt for context.
     */
    public function withOriginalPrompt(string $prompt): self
    {
        $clone = clone $this;
        $clone->originalPrompt = $prompt;

        return $clone;
    }

    /**
     * Inject the full Conversation for context-aware judging.
     */
    #[Override]
    public function withConversation(Conversation $conversation): self
    {
        $clone = clone $this;
        $clone->conversation = $conversation;

        return $clone;
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        $judgePrompt = $this->buildJudgePrompt($text);

        $options = [];
        if ($this->model !== null) {
            $options['model'] = $this->model;
        }

        $response = $this->judge->complete($judgePrompt, $options);
        $judgment = $this->parseJudgment($response->text);

        if ($judgment === null) {
            return AssertionResult::fail(
                $this->getDescription(),
                'Failed to parse judge response: ' . $response->text,
            );
        }

        $percentage = (int) ($judgment['score'] * 100);
        $thresholdPercent = (int) ($this->threshold * 100);
        $message = sprintf(
            'Score: %d%% (threshold: %d%%) - %s',
            $percentage,
            $thresholdPercent,
            $judgment['reasoning'],
        );

        if ($judgment['pass'] && $judgment['score'] >= $this->threshold) {
            return new AssertionResult(true, $this->getDescription(), $message);
        }

        return AssertionResult::fail($this->getDescription(), $message);
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Judged by LLM: "%s" (threshold: %.0f%%)', $this->criteria, $this->threshold * 100);
    }

    /**
     * Build the judge prompt, using conversation context when available.
     */
    private function buildJudgePrompt(string $text): string
    {
        if ($this->conversation !== null) {
            return sprintf(
                self::CONVERSATION_JUDGE_PROMPT,
                $this->formatConversationHistory(),
                $this->originalPrompt ?: '(not provided)',
                $text,
                $this->criteria,
            );
        }

        return sprintf(
            self::JUDGE_PROMPT,
            $this->originalPrompt ?: '(not provided)',
            $text,
            $this->criteria,
        );
    }

    /**
     * Format conversation messages as readable history for the judge.
     */
    private function formatConversationHistory(): string
    {
        assert($this->conversation !== null);

        $lines = [];
        foreach ($this->conversation->getMessages() as $message) {
            if ($message->hasToolResults()) {
                continue; // Skip raw tool result messages.
            }

            $role = $message->role === \Aysnc\AI\LlmEval\Providers\Role::User ? 'User' : 'Assistant';

            if ($message->text !== '') {
                $lines[] = "{$role}: {$message->text}";
            }

            foreach ($message->toolCalls as $tc) {
                $lines[] = "Assistant: [called tool {$tc->name}]";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parse the judge's JSON response.
     *
     * @return array{pass: bool, score: float, reasoning: string}|null
     */
    private function parseJudgment(string $response): ?array
    {
        // Try to extract JSON from the response (it might have extra text)
        if (preg_match('/\{[^}]+\}/', $response, $matches)) {
            $json = $matches[0];
        } else {
            $json = $response;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return null;
        }

        $pass = $data['pass'] ?? null;
        $score = $data['score'] ?? null;
        $reasoning = $data['reasoning'] ?? '';

        if (!is_bool($pass) && !is_string($pass)) {
            return null;
        }

        if (!is_float($score) && !is_int($score)) {
            return null;
        }

        return [
            'pass' => is_bool($pass) ? $pass : ($pass === 'true'),
            'score' => (float) $score,
            'reasoning' => is_string($reasoning) ? $reasoning : '',
        ];
    }
}
