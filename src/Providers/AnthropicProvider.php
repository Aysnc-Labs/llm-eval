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
use GuzzleHttp\Promise\PromiseInterface;
use RuntimeException;

/**
 * Provider for Anthropic's Claude API.
 *
 * Usage:
 *   $provider = new AnthropicProvider('your-api-key');
 *   $response = $provider->complete('What is 2+2?');
 *   echo $response->text; // "4"
 */
class AnthropicProvider implements AsyncConversableProviderInterface
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
        $result = $this->completeAsync($prompt, $options)->wait();

        if (!$result instanceof Response) {
            throw new RuntimeException('Unexpected response type from async completion');
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function completeAsync(string $prompt, array $options = []): PromiseInterface
    {
        return $this->completeWithMessagesAsync([Message::user($prompt)], $options);
    }

    /**
     * @inheritDoc
     */
    public function completeWithMessages(array $messages, array $options = []): Response
    {
        $result = $this->completeWithMessagesAsync($messages, $options)->wait();

        if (!$result instanceof Response) {
            throw new RuntimeException('Unexpected response type from async completion');
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function completeWithMessagesAsync(array $messages, array $options = []): PromiseInterface
    {
        $model = is_string($options['model'] ?? null) ? $options['model'] : self::DEFAULT_MODEL;
        $maxTokens = is_int($options['max_tokens'] ?? null) ? $options['max_tokens'] : self::DEFAULT_MAX_TOKENS;

        $json = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $this->convertMessages($messages),
        ];

        // Add tools if provided (Anthropic format)
        if (isset($options['tools']) && is_array($options['tools'])) {
            $json['tools'] = $options['tools'];
        }

        return $this->client->requestAsync('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => $json,
        ])->then(function (mixed $response) use ($model): Response {
            if (!$response instanceof \Psr\Http\Message\ResponseInterface) {
                throw new RuntimeException('Invalid response type from HTTP client');
            }

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                throw new RuntimeException('Invalid JSON response from Anthropic API');
            }

            /** @var array<string, mixed> $data */
            return $this->buildResponse($data, $model);
        });
    }

    /**
     * Convert Message objects to Anthropic API message format.
     *
     * @param array<Message> $messages
     * @return array<array<string, mixed>>
     */
    private function convertMessages(array $messages): array
    {
        $converted = [];

        foreach ($messages as $message) {
            if ($message->hasToolResults()) {
                // Tool results are sent as user role with tool_result content blocks
                $content = [];
                foreach ($message->toolResults as $result) {
                    $block = [
                        'type' => 'tool_result',
                        'tool_use_id' => $result->toolCallId,
                        'content' => $result->content,
                    ];
                    if ($result->isError) {
                        $block['is_error'] = true;
                    }
                    $content[] = $block;
                }
                $converted[] = ['role' => 'user', 'content' => $content];
            } elseif ($message->role === Role::Assistant && $message->hasToolCalls()) {
                // Assistant message with tool calls needs text + tool_use content blocks
                $content = [];
                if ($message->text !== '') {
                    $content[] = ['type' => 'text', 'text' => $message->text];
                }
                foreach ($message->toolCalls as $toolCall) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $toolCall->id,
                        'name' => $toolCall->name,
                        'input' => $toolCall->input,
                    ];
                }
                $converted[] = ['role' => 'assistant', 'content' => $content];
            } else {
                // Simple text message (user or assistant)
                $converted[] = ['role' => $message->role->value, 'content' => $message->text];
            }
        }

        return $converted;
    }

    /**
     * Build a Response from API data.
     *
     * @param array<string, mixed> $data The decoded API response.
     * @param string $fallbackModel Model to use if not in response.
     */
    private function buildResponse(array $data, string $fallbackModel): Response
    {
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        $inputTokens = is_int($usage['input_tokens'] ?? null) ? $usage['input_tokens'] : 0;
        $outputTokens = is_int($usage['output_tokens'] ?? null) ? $usage['output_tokens'] : 0;

        return new Response(
            text: $this->extractText($data),
            model: is_string($data['model'] ?? null) ? $data['model'] : $fallbackModel,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            raw: $data,
            toolCalls: $this->extractToolCalls($data),
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

    /**
     * Extract tool calls from the API response.
     *
     * Anthropic returns tool calls as content blocks with type "tool_use":
     * ```json
     * {
     *   "type": "tool_use",
     *   "id": "toolu_01A09q90qw90lq917835lqub",
     *   "name": "get_weather",
     *   "input": {"location": "San Francisco", "unit": "celsius"}
     * }
     * ```
     *
     * @param array<string, mixed> $data The decoded API response.
     * @return array<ToolCall>
     */
    private function extractToolCalls(array $data): array
    {
        $content = $data['content'] ?? [];

        if (!is_array($content)) {
            return [];
        }

        $toolCalls = [];

        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') !== 'tool_use') {
                continue;
            }

            $id = $block['id'] ?? '';
            $name = $block['name'] ?? '';
            $input = $block['input'] ?? [];

            if (!is_string($id) || !is_string($name) || !is_array($input)) {
                continue;
            }

            // Anthropic API always returns input as a JSON object (string keys)
            /** @var array<string, mixed> $input */
            $toolCalls[] = new ToolCall(
                id: $id,
                name: $name,
                input: $input,
            );
        }

        return $toolCalls;
    }
}
