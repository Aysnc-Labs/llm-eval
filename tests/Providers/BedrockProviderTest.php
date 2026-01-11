<?php

/**
 * Tests for the BedrockProvider class.
 *
 * These tests require the AWS SDK to be installed.
 * Run: composer require --dev aws/aws-sdk-php
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\BedrockProvider;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use GuzzleHttp\Promise\FulfilledPromise;
use PHPUnit\Framework\TestCase;

/**
 * Test case for BedrockProvider using the Converse API.
 *
 * @requires extension json
 */
class BedrockProviderTest extends TestCase
{
    /**
     * Check if AWS SDK is available before running tests.
     */
    protected function setUp(): void
    {
        if (!class_exists(\Aws\BedrockRuntime\BedrockRuntimeClient::class)) {
            $this->markTestSkipped('AWS SDK is not installed. Run: composer require --dev aws/aws-sdk-php');
        }
    }

    /**
     * Test that complete returns a Response with text.
     */
    public function testCompleteReturnsResponse(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'The answer is 4.'],
                    ],
                ],
            ],
            'stopReason' => 'end_turn',
            'usage' => [
                'inputTokens' => 10,
                'outputTokens' => 5,
            ],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('What is 2+2?');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('The answer is 4.', $response->text);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(5, $response->outputTokens);
        $this->assertSame(15, $response->totalTokens());
    }

    /**
     * Test that custom model can be passed via options.
     */
    public function testCompleteWithCustomModel(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Hello!'],
                    ],
                ],
            ],
            'usage' => ['inputTokens' => 5, 'outputTokens' => 2],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('Hi', ['model' => 'amazon.titan-text-express-v1']);

        // Model comes from options since Converse API doesn't return it
        $this->assertSame('amazon.titan-text-express-v1', $response->model);
    }

    /**
     * Test that tool calls are extracted from response.
     */
    public function testCompleteExtractsToolCalls(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'toolUse' => [
                                'toolUseId' => 'tooluse_bdrk_01A09q90qw90lq917835lqub',
                                'name' => 'get_weather',
                                'input' => ['location' => 'San Francisco', 'unit' => 'celsius'],
                            ],
                        ],
                    ],
                ],
            ],
            'stopReason' => 'tool_use',
            'usage' => ['inputTokens' => 10, 'outputTokens' => 5],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('What is the weather?');

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);

        $toolCall = $response->toolCalls[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('tooluse_bdrk_01A09q90qw90lq917835lqub', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['location' => 'San Francisco', 'unit' => 'celsius'], $toolCall->input);
    }

    /**
     * Test that multiple tool calls are extracted.
     */
    public function testCompleteExtractsMultipleToolCalls(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'toolUse' => [
                                'toolUseId' => 'tooluse_1',
                                'name' => 'get_weather',
                                'input' => ['location' => 'NYC'],
                            ],
                        ],
                        [
                            'toolUse' => [
                                'toolUseId' => 'tooluse_2',
                                'name' => 'send_email',
                                'input' => ['to' => 'test@example.com', 'body' => 'Hello'],
                            ],
                        ],
                    ],
                ],
            ],
            'stopReason' => 'tool_use',
            'usage' => ['inputTokens' => 10, 'outputTokens' => 10],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('Check weather and send email');

        $this->assertCount(2, $response->toolCalls);
        $this->assertSame('get_weather', $response->toolCalls[0]->name);
        $this->assertSame('send_email', $response->toolCalls[1]->name);
    }

    /**
     * Test that text and tool calls can coexist.
     */
    public function testCompleteExtractsTextAndToolCalls(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Let me check the weather for you.'],
                        [
                            'toolUse' => [
                                'toolUseId' => 'tooluse_1',
                                'name' => 'get_weather',
                                'input' => ['location' => 'NYC'],
                            ],
                        ],
                    ],
                ],
            ],
            'stopReason' => 'tool_use',
            'usage' => ['inputTokens' => 10, 'outputTokens' => 15],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('What is the weather?');

        $this->assertSame('Let me check the weather for you.', $response->text);
        $this->assertTrue($response->hasToolCalls());
        $this->assertSame('get_weather', $response->toolCalls[0]->name);
    }

    /**
     * Test that response without tool calls has empty array.
     */
    public function testCompleteWithNoToolCalls(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Hello!'],
                    ],
                ],
            ],
            'stopReason' => 'end_turn',
            'usage' => ['inputTokens' => 5, 'outputTokens' => 2],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('Hi');

        $this->assertFalse($response->hasToolCalls());
        $this->assertSame([], $response->toolCalls);
    }

    /**
     * Test that tool calls with empty input are handled.
     */
    public function testCompleteWithEmptyToolInput(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'toolUse' => [
                                'toolUseId' => 'tooluse_1',
                                'name' => 'get_current_time',
                                'input' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'stopReason' => 'tool_use',
            'usage' => ['inputTokens' => 5, 'outputTokens' => 5],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->complete('What time is it?');

        $this->assertTrue($response->hasToolCalls());
        $this->assertSame([], $response->toolCalls[0]->input);
    }

    /**
     * Create a mock Bedrock client with a predefined Converse API response.
     *
     * @param array<string, mixed> $responseData The response data to return.
     * @return \Aws\BedrockRuntime\BedrockRuntimeClient
     */
    private function createMockBedrockClient(array $responseData): object
    {
        // Create a mock that returns the response as an AWS Result-like object
        $mockResult = new class ($responseData) {
            /** @var array<string, mixed> */
            private array $data;

            /**
             * @param array<string, mixed> $data
             */
            public function __construct(array $data)
            {
                $this->data = $data;
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return $this->data;
            }
        };

        // Create a mock client that returns the result wrapped in a promise
        // AWS SDK uses __call for async methods, so we need addMethods()
        $mockClient = $this->getMockBuilder(\Aws\BedrockRuntime\BedrockRuntimeClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['converseAsync'])
            ->getMock();

        $mockClient->method('converseAsync')
            ->willReturn(new FulfilledPromise($mockResult));

        return $mockClient;
    }
}
