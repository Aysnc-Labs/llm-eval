<?php

/**
 * Anthropic (Claude) provider implementation.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * Provider for Anthropic's Claude API.
 *
 * Usage:
 *   $provider = new AnthropicProvider('your-api-key');
 *   $response = $provider->complete('What is 2+2?');
 *   echo $response->text; // "4"
 */
class AnthropicProvider implements ProviderInterface
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';
    private const string API_VERSION = '2023-06-01';
    private const string DEFAULT_MODEL = 'claude-sonnet-4-20250514';
    private const int DEFAULT_MAX_TOKENS = 1024;

    private ClientInterface $client;

    /**
     * @param string $apiKey Your Anthropic API key.
     * @param ClientInterface|null $client Optional HTTP client (for testing).
     */
    public function __construct(
        private readonly string $apiKey,
        ?ClientInterface $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    /**
     * @inheritDoc
     */
    public function complete(string $prompt, array $options = []): Response
    {
        $model = is_string($options['model'] ?? null) ? $options['model'] : self::DEFAULT_MODEL;
        $maxTokens = is_int($options['max_tokens'] ?? null) ? $options['max_tokens'] : self::DEFAULT_MAX_TOKENS;

        $response = $this->client->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response from Anthropic API');
        }

        /** @var array<string, mixed> $data */
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        $inputTokens = is_int($usage['input_tokens'] ?? null) ? $usage['input_tokens'] : 0;
        $outputTokens = is_int($usage['output_tokens'] ?? null) ? $usage['output_tokens'] : 0;

        return new Response(
            text: $this->extractText($data),
            model: is_string($data['model'] ?? null) ? $data['model'] : $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            raw: $data,
        );
    }

    /**
     * Extract text content from the API response.
     *
     * @param array<string, mixed> $data The decoded API response.
     */
    private function extractText(array $data): string
    {
        $content = $data['content'] ?? [];

        if (!is_array($content)) {
            return '';
        }

        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                return $block['text'];
            }
        }

        return '';
    }
}
