<?php

/**
 * AWS Bedrock provider using the Converse API.
 *
 * Works with any Bedrock model: Claude, Titan, Llama, Mistral, etc.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Providers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use GuzzleHttp\Promise\PromiseInterface;
use RuntimeException;

/**
 * Provider for models via AWS Bedrock Converse API.
 *
 * Uses the unified Converse API which works across all Bedrock models
 * with a standardized request/response format.
 *
 * Requires the AWS SDK for PHP: composer require aws/aws-sdk-php
 *
 * Usage with explicit credentials:
 *   $provider = new BedrockProvider(
 *       region: 'us-east-1',
 *       accessKeyId: 'AKIA...',
 *       secretAccessKey: 'secret...',
 *   );
 *   $response = $provider->complete('What is 2+2?');
 *
 * Usage with default credential chain (env vars, ~/.aws/credentials, IAM role):
 *   $provider = new BedrockProvider(region: 'us-east-1');
 */
class BedrockProvider implements AsyncProviderInterface
{
    private const string DEFAULT_MODEL = 'anthropic.claude-sonnet-4-20250514-v1:0';
    private const int DEFAULT_MAX_TOKENS = 1024;

    private BedrockRuntimeClient $client;

    /**
     * @param string $region AWS region (e.g., 'us-east-1').
     * @param string|null $accessKeyId AWS access key ID (null to use default credential chain).
     * @param string|null $secretAccessKey AWS secret access key.
     * @param BedrockRuntimeClient|null $client Optional client for testing.
     */
    public function __construct(
        string $region,
        ?string $accessKeyId = null,
        ?string $secretAccessKey = null,
        ?BedrockRuntimeClient $client = null,
    ) {
        if ($client !== null) {
            $this->client = $client;
            return;
        }

        if (!class_exists(BedrockRuntimeClient::class)) {
            throw new RuntimeException(
                'AWS SDK is required for BedrockProvider. Install with: composer require aws/aws-sdk-php'
            );
        }

        $config = [
            'region' => $region,
            'version' => 'latest',
        ];

        // Use explicit credentials if provided, otherwise SDK uses default chain
        if ($accessKeyId !== null && $secretAccessKey !== null) {
            $config['credentials'] = [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ];
        }

        $this->client = new BedrockRuntimeClient($config);
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
        $model = is_string($options['model'] ?? null) ? $options['model'] : self::DEFAULT_MODEL;
        $maxTokens = is_int($options['max_tokens'] ?? null) ? $options['max_tokens'] : self::DEFAULT_MAX_TOKENS;

        $request = [
            'modelId' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'inferenceConfig' => [
                'maxTokens' => $maxTokens,
            ],
        ];

        // Add tools if provided (convert to Converse API format)
        if (isset($options['tools']) && is_array($options['tools'])) {
            /** @var array<array<string, mixed>> $tools */
            $tools = $options['tools'];
            $request['toolConfig'] = [
                'tools' => $this->convertToolsToConverseFormat($tools),
            ];
        }

        return $this->client->converseAsync($request)->then(function ($result) use ($model): Response {
            /** @var \Aws\Result<array<string, mixed>> $result */
            /** @var array<string, mixed> $data */
            $data = $result->toArray();

            return $this->buildResponse($data, $model);
        });
    }

    /**
     * Convert tool definitions from Anthropic format to Converse API format.
     *
     * Anthropic format:
     *   ['name' => 'x', 'description' => 'y', 'input_schema' => [...]]
     *
     * Converse format:
     *   ['toolSpec' => ['name' => 'x', 'description' => 'y', 'inputSchema' => ['json' => [...]]]]
     *
     * @param array<array<string, mixed>> $tools
     * @return array<array<string, mixed>>
     */
    private function convertToolsToConverseFormat(array $tools): array
    {
        $converted = [];

        foreach ($tools as $tool) {
            $spec = [
                'name' => $tool['name'] ?? '',
                'description' => $tool['description'] ?? '',
            ];

            // Convert input_schema to inputSchema.json
            if (isset($tool['input_schema']) && is_array($tool['input_schema'])) {
                $spec['inputSchema'] = ['json' => $tool['input_schema']];
            }

            $converted[] = ['toolSpec' => $spec];
        }

        return $converted;
    }

    /**
     * Build a Response from Converse API data.
     *
     * @param array<string, mixed> $data The API response.
     * @param string $fallbackModel Model to use if not in response.
     */
    private function buildResponse(array $data, string $fallbackModel): Response
    {
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        $inputTokens = is_int($usage['inputTokens'] ?? null) ? $usage['inputTokens'] : 0;
        $outputTokens = is_int($usage['outputTokens'] ?? null) ? $usage['outputTokens'] : 0;

        return new Response(
            text: $this->extractText($data),
            model: $fallbackModel,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            raw: $data,
            toolCalls: $this->extractToolCalls($data),
        );
    }

    /**
     * Extract text content from the Converse API response.
     *
     * @param array<string, mixed> $data The API response.
     */
    private function extractText(array $data): string
    {
        $output = $data['output'] ?? [];
        if (!is_array($output)) {
            return '';
        }

        $message = $output['message'] ?? [];
        if (!is_array($message)) {
            return '';
        }

        $content = $message['content'] ?? [];
        if (!is_array($content)) {
            return '';
        }

        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (isset($block['text']) && is_string($block['text'])) {
                return $block['text'];
            }
        }

        return '';
    }

    /**
     * Extract tool calls from the Converse API response.
     *
     * Converse API returns tool calls as:
     * {
     *   "toolUse": {
     *     "toolUseId": "id",
     *     "name": "tool_name",
     *     "input": {...}
     *   }
     * }
     *
     * @param array<string, mixed> $data The API response.
     * @return array<ToolCall>
     */
    private function extractToolCalls(array $data): array
    {
        $output = $data['output'] ?? [];
        if (!is_array($output)) {
            return [];
        }

        $message = $output['message'] ?? [];
        if (!is_array($message)) {
            return [];
        }

        $content = $message['content'] ?? [];
        if (!is_array($content)) {
            return [];
        }

        $toolCalls = [];

        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }

            $toolUse = $block['toolUse'] ?? null;
            if (!is_array($toolUse)) {
                continue;
            }

            $id = $toolUse['toolUseId'] ?? '';
            $name = $toolUse['name'] ?? '';
            $input = $toolUse['input'] ?? [];

            if (!is_string($id) || !is_string($name) || !is_array($input)) {
                continue;
            }

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
