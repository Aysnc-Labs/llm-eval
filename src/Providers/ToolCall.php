<?php

/**
 * Value object for LLM tool calls.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Immutable value object representing a tool call from an LLM response.
 *
 * When an LLM decides to call a tool, it returns a structured block with
 * the tool name and input parameters. This class represents that parsed data.
 *
 * Anthropic format:
 * ```json
 * {
 *   "type": "tool_use",
 *   "id": "toolu_01A09q90qw90lq917835lqub",
 *   "name": "get_weather",
 *   "input": {"location": "San Francisco", "unit": "celsius"}
 * }
 * ```
 */
readonly class ToolCall
{
    /**
     * @param string $id Unique identifier for this tool call (used for tool_result matching).
     * @param string $name The name of the tool being called.
     * @param array<string, mixed> $input The input parameters for the tool.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $input = [],
    ) {
    }

    /**
     * Check if this tool call has a specific parameter.
     */
    public function hasParam(string $name): bool
    {
        return array_key_exists($name, $this->input);
    }

    /**
     * Get a parameter value, or null if it doesn't exist.
     */
    public function getParam(string $name): mixed
    {
        return $this->input[$name] ?? null;
    }
}
