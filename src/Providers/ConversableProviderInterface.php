<?php

/**
 * Interface for providers that support multi-turn conversations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Extended provider interface for message history support.
 *
 * Providers implementing this interface can accept a full conversation
 * history (array of Message objects) instead of just a single prompt string.
 * This is the foundation for multi-turn conversations and agentic loops.
 *
 * This is a separate interface to avoid breaking existing ProviderInterface
 * consumers. Code that needs conversations type-hints this interface.
 */
interface ConversableProviderInterface extends ProviderInterface
{
    /**
     * Send a conversation history to the LLM and get a response.
     *
     * @param array<Message> $messages The conversation messages.
     * @param array<string, mixed> $options Provider-specific options.
     *
     * @return Response The LLM response.
     */
    public function completeWithMessages(array $messages, array $options = []): Response;
}
