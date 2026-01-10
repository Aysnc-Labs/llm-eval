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
     */
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public array $raw = [],
    ) {
    }

    /**
     * Get total token usage.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
