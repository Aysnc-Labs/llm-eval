<?php

/**
 * Interface for async providers that support multi-turn conversations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Extended async provider interface for message history support.
 *
 * Combines both async and conversable capabilities. Providers like
 * AnthropicProvider and BedrockProvider implement this interface.
 */
interface AsyncConversableProviderInterface extends ConversableProviderInterface, AsyncProviderInterface
{
    /**
     * Send a conversation history asynchronously.
     *
     * @param array<Message> $messages The conversation messages.
     * @param array<string, mixed> $options Provider-specific options.
     *
     * @return PromiseInterface A promise that resolves to a Response.
     */
    public function completeWithMessagesAsync(array $messages, array $options = []): PromiseInterface;
}
