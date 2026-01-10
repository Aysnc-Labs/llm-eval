<?php

/**
 * Interface for async-capable providers.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Extended provider interface for async operations.
 *
 * Providers implementing this interface can run requests concurrently.
 */
interface AsyncProviderInterface extends ProviderInterface
{
    /**
     * Send a prompt asynchronously.
     *
     * @param string $prompt The prompt to send.
     * @param array<string, mixed> $options Provider-specific options.
     *
     * @return PromiseInterface<Response> A promise that resolves to a Response.
     */
    public function completeAsync(string $prompt, array $options = []): PromiseInterface;
}
