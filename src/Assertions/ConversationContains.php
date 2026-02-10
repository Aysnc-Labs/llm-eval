<?php

/**
 * ConversationContains assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Conversation\Conversation;
use LogicException;
use Override;

/**
 * Asserts that a substring appears in any message of the conversation.
 *
 * Checks all messages (user, assistant, tool results) for the needle.
 * Useful for verifying that specific content appeared at any point
 * during a multi-turn conversation, not just in the final response.
 */
readonly class ConversationContains implements ConversationAwareAssertion
{
    /**
     * @param string $needle The text to search for.
     * @param Conversation|null $conversation The conversation to check (injected via withConversation).
     */
    public function __construct(
        private string $needle,
        private ?Conversation $conversation = null,
    ) {
    }

    #[Override]
    public function withConversation(Conversation $conversation): self
    {
        return new self($this->needle, $conversation);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->conversation === null) {
            throw new LogicException('Conversation not set. ConversationContains requires withConversation() to be called first.');
        }

        foreach ($this->conversation->getMessages() as $message) {
            if (str_contains($message->text, $this->needle)) {
                return AssertionResult::pass($this->getDescription());
            }
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Text "%s" not found in any conversation message', $this->needle),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Conversation contains "%s"', $this->needle);
    }
}
