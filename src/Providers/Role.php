<?php

/**
 * Enum for message roles in a conversation.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Represents the role of a message in a conversation.
 *
 * Both Anthropic and Bedrock APIs use 'user' and 'assistant' roles.
 * Tool results are sent as 'user' role messages with special content blocks,
 * so no separate 'tool_result' role is needed.
 */
enum Role: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
