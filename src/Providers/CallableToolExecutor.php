<?php

/**
 * Closure-based tool executor that maps tool names to callables.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

use RuntimeException;

readonly class CallableToolExecutor implements ToolExecutorInterface
{
    /**
     * @param array<string, callable(ToolCall): ToolResult> $tools Map of tool name to callable.
     */
    public function __construct(
        private array $tools,
    ) {
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        if (! isset($this->tools[$toolCall->name])) {
            throw new RuntimeException("Unknown tool: {$toolCall->name}");
        }

        return ($this->tools[$toolCall->name])($toolCall);
    }
}
