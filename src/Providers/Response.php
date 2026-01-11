<?php

/**
 * Value object for LLM responses.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Immutable value object representing an LLM response.
 *
 * Contains the response text plus metadata like token usage.
 * This is what assertions will run against.
 */
readonly class Response
{
    /**
     * @param string $text The response text from the LLM.
     * @param string $model The model that generated the response.
     * @param int $inputTokens Number of tokens in the prompt.
     * @param int $outputTokens Number of tokens in the response.
     * @param array<string, mixed> $raw The raw API response for debugging.
     * @param array<ToolCall> $toolCalls Tool calls made by the LLM (if any).
     */
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public array $raw = [],
        public array $toolCalls = [],
    ) {
    }

    /**
     * Check if the response contains any tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Get a tool call by name (first match).
     */
    public function getToolCall(string $name): ?ToolCall
    {
        foreach ($this->toolCalls as $toolCall) {
            if ($toolCall->name === $name) {
                return $toolCall;
            }
        }

        return null;
    }

    /**
     * Get all tool calls with a specific name.
     *
     * @return array<ToolCall>
     */
    public function getToolCalls(string $name): array
    {
        return array_values(array_filter(
            $this->toolCalls,
            fn (ToolCall $tc) => $tc->name === $name,
        ));
    }

    /**
     * Get total token usage.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
