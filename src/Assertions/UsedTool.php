<?php

/**
 * UsedTool assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Conversation\Conversation;
use LogicException;
use Override;

/**
 * Asserts that a tool was used in any turn of a conversation.
 *
 * Unlike CalledTool (which checks a single response), this checks
 * across ALL responses in the conversation. Useful for verifying
 * that the LLM used a specific tool at some point during the
 * multi-turn interaction.
 */
readonly class UsedTool implements ConversationAwareAssertion
{
    /**
     * @param string $toolName The name of the tool to check for.
     * @param Conversation|null $conversation The conversation to check (injected via withConversation).
     */
    public function __construct(
        private string $toolName,
        private ?Conversation $conversation = null,
    ) {
    }

    #[Override]
    public function withConversation(Conversation $conversation): self
    {
        return new self($this->toolName, $conversation);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->conversation === null) {
            throw new LogicException('Conversation not set. UsedTool requires withConversation() to be called first.');
        }

        foreach ($this->conversation->getResponses() as $response) {
            if ($response->getToolCall($this->toolName) !== null) {
                return AssertionResult::pass($this->getDescription());
            }
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Tool "%s" was not used in any turn', $this->toolName),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Tool "%s" was used during conversation', $this->toolName);
    }
}
