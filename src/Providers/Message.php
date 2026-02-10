<?php

/**
 * Value object for conversation messages.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Immutable value object representing one message in a conversation.
 *
 * Messages can be user prompts, assistant responses (with optional tool calls),
 * or tool result messages. Use the static factory methods to create them.
 *
 * Usage:
 *   // Simple user message
 *   $msg = Message::user('What is the weather?');
 *
 *   // From an LLM response (preserves tool calls)
 *   $msg = Message::fromResponse($response);
 *
 *   // Tool results to send back
 *   $msg = Message::toolResults([
 *       new ToolResult('toolu_1', '{"temp": 72}'),
 *   ]);
 */
readonly class Message
{
    /**
     * @param Role $role The role of this message.
     * @param string $text The text content of this message.
     * @param array<ToolCall> $toolCalls Tool calls made by the assistant (if any).
     * @param array<ToolResult> $toolResults Tool results being sent back (if any).
     */
    public function __construct(
        public Role $role,
        public string $text = '',
        public array $toolCalls = [],
        public array $toolResults = [],
    ) {
    }

    /**
     * Create a user message.
     */
    public static function user(string $text): self
    {
        return new self(role: Role::User, text: $text);
    }

    /**
     * Create an assistant message from an LLM response.
     *
     * Preserves both text and tool calls from the response,
     * which is needed for multi-turn conversations where the
     * API requires the full assistant message to be echoed back.
     */
    public static function fromResponse(Response $response): self
    {
        return new self(
            role: Role::Assistant,
            text: $response->text,
            toolCalls: $response->toolCalls,
        );
    }

    /**
     * Create a tool results message.
     *
     * Tool results are sent as user-role messages with special content blocks.
     * Both Anthropic and Bedrock handle this the same way conceptually.
     *
     * @param array<ToolResult> $results The tool execution results.
     */
    public static function toolResults(array $results): self
    {
        return new self(role: Role::User, toolResults: $results);
    }

    /**
     * Check if this message contains tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Check if this message contains tool results.
     */
    public function hasToolResults(): bool
    {
        return count($this->toolResults) > 0;
    }

    /**
     * Convert to array for deterministic cache key serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
            'text' => $this->text,
        ];

        if ($this->toolCalls !== []) {
            $data['tool_calls'] = array_map(
                fn (ToolCall $tc) => [
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'input' => $tc->input,
                ],
                $this->toolCalls,
            );
        }

        if ($this->toolResults !== []) {
            $data['tool_results'] = array_map(
                fn (ToolResult $tr) => [
                    'tool_call_id' => $tr->toolCallId,
                    'content' => $tr->content,
                    'is_error' => $tr->isError,
                ],
                $this->toolResults,
            );
        }

        return $data;
    }
}
