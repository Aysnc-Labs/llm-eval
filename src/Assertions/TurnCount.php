<?php

/**
 * TurnCount assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Conversation\Conversation;
use LogicException;
use Override;

/**
 * Asserts the number of LLM calls (turns) in a conversation.
 *
 * Each call to the provider counts as one turn. A simple
 * request-response is 1 turn. A tool loop with one round-trip
 * is 2 turns (initial + after tool results).
 */
readonly class TurnCount implements ConversationAwareAssertion
{
    /**
     * @param int $expected The expected number of turns.
     * @param Conversation|null $conversation The conversation to check (injected via withConversation).
     */
    public function __construct(
        private int $expected,
        private ?Conversation $conversation = null,
    ) {
    }

    #[Override]
    public function withConversation(Conversation $conversation): self
    {
        return new self($this->expected, $conversation);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->conversation === null) {
            throw new LogicException('Conversation not set. TurnCount requires withConversation() to be called first.');
        }

        $actual = count($this->conversation->getResponses());

        if ($actual === $this->expected) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Expected %d turn(s), got %d', $this->expected, $actual),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Conversation has %d turn(s)', $this->expected);
    }
}
