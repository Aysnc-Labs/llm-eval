<?php

/**
 * Provider interface for LLM communication.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

/**
 * Contract that all LLM providers must implement.
 *
 * Providers handle the actual communication with LLM APIs.
 * Each provider (Anthropic, OpenAI, etc.) implements this interface.
 */
interface ProviderInterface
{
    /**
     * Send a prompt to the LLM and get a response.
     *
     * @param string $prompt The prompt to send.
     * @param array<string, mixed> $options Provider-specific options (model, temperature, etc.).
     *
     * @return Response The LLM response.
     */
    public function complete(string $prompt, array $options = []): Response;
}
