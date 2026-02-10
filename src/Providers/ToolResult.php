<?php

/**
 * Value object for tool execution results.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Immutable value object representing the result of executing a tool.
 *
 * A ToolCall is what the LLM asks to execute.
 * A ToolResult is what we send back with the execution output.
 *
 * Usage:
 *   $result = new ToolResult(
 *       toolCallId: 'toolu_01A09q90qw90lq917835lqub',
 *       content: '{"temperature": 72, "unit": "fahrenheit"}',
 *   );
 */
readonly class ToolResult
{
    /**
     * @param string $toolCallId The ID of the tool call this result responds to.
     * @param string $content The result content (typically a string or JSON).
     * @param bool $isError Whether the tool execution resulted in an error.
     */
    public function __construct(
        public string $toolCallId,
        public string $content,
        public bool $isError = false,
    ) {
    }
}
