<?php

/**
 * Contract for executing tool calls during multi-turn conversations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

interface ToolExecutorInterface
{
    /**
     * Execute a tool call and return the result.
     */
    public function execute(ToolCall $toolCall): ToolResult;
}
