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
use Aysnc\AI\LlmEval\Providers\Message;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;
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
     * Test completeWithMessages with a simple user message.
     */
    public function testCompleteWithMessagesSimple(): void
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
            'usage' => ['inputTokens' => 10, 'outputTokens' => 5],
        ];

        $client = $this->createMockBedrockClient($mockResponse);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $response = $provider->completeWithMessages([Message::user('What is 2+2?')]);

        $this->assertSame('The answer is 4.', $response->text);
    }

    /**
     * Test completeWithMessages with multi-turn conversation.
     */
    public function testCompleteWithMessagesMultiTurn(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Paris is the capital of France.'],
                    ],
                ],
            ],
            'usage' => ['inputTokens' => 20, 'outputTokens' => 10],
        ];

        /** @var array<string, mixed>|null $capturedRequest */
        $capturedRequest = null;
        $client = $this->createMockBedrockClientWithCapture($mockResponse, $capturedRequest);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $messages = [
            Message::user('What is the capital of France?'),
            Message::fromResponse(new Response(text: 'The capital is Paris.', model: 'test')),
            Message::user('Are you sure?'),
        ];

        $response = $provider->completeWithMessages($messages);

        $this->assertSame('Paris is the capital of France.', $response->text);

        // Verify request structure
        $this->assertNotNull($capturedRequest);
        /** @var array<int, array{role: string, content: array<int, array<string, mixed>>}> $messages */
        $messages = $capturedRequest['messages'];
        $this->assertCount(3, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('user', $messages[2]['role']);

        // Bedrock uses content array format
        $this->assertSame('What is the capital of France?', $messages[0]['content'][0]['text']);
        $this->assertSame('The capital is Paris.', $messages[1]['content'][0]['text']);
    }

    /**
     * Test completeWithMessages with tool result messages.
     */
    public function testCompleteWithMessagesToolResults(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'The weather in NYC is 72F.'],
                    ],
                ],
            ],
            'usage' => ['inputTokens' => 30, 'outputTokens' => 15],
        ];

        /** @var array<string, mixed>|null $capturedRequest */
        $capturedRequest = null;
        $client = $this->createMockBedrockClientWithCapture($mockResponse, $capturedRequest);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $messages = [
            Message::user('What is the weather in NYC?'),
            Message::fromResponse(new Response(
                text: 'Let me check.',
                model: 'test',
                toolCalls: [new ToolCall(id: 'tooluse_1', name: 'get_weather', input: ['location' => 'NYC'])],
            )),
            Message::toolResults([
                new ToolResult(toolCallId: 'tooluse_1', content: '72F and sunny'),
            ]),
        ];

        $response = $provider->completeWithMessages($messages);

        $this->assertSame('The weather in NYC is 72F.', $response->text);

        // Verify request format
        $this->assertNotNull($capturedRequest);
        /** @var array<int, array{role: string, content: array<int, array<string, mixed>>}> $msgs */
        $msgs = $capturedRequest['messages'];

        // Assistant message should have toolUse blocks
        $assistantContent = $msgs[1]['content'];
        $this->assertSame('Let me check.', $assistantContent[0]['text']);
        /** @var array{toolUseId: string, name: string, input: array<string, mixed>} $toolUse */
        $toolUse = $assistantContent[1]['toolUse'];
        $this->assertSame('tooluse_1', $toolUse['toolUseId']);
        $this->assertSame('get_weather', $toolUse['name']);

        // Tool result message should have toolResult blocks
        $toolResultContent = $msgs[2]['content'];
        /** @var array{toolUseId: string, content: array<int, array{text: string}>} $toolResult */
        $toolResult = $toolResultContent[0]['toolResult'];
        $this->assertSame('tooluse_1', $toolResult['toolUseId']);
        $this->assertSame('72F and sunny', $toolResult['content'][0]['text']);
    }

    /**
     * Test that tool result errors include status field.
     */
    public function testCompleteWithMessagesToolResultError(): void
    {
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Sorry, the tool failed.'],
                    ],
                ],
            ],
            'usage' => ['inputTokens' => 20, 'outputTokens' => 10],
        ];

        /** @var array<string, mixed>|null $capturedRequest */
        $capturedRequest = null;
        $client = $this->createMockBedrockClientWithCapture($mockResponse, $capturedRequest);
        $provider = new BedrockProvider('us-east-1', null, null, $client);

        $messages = [
            Message::user('Check the weather'),
            Message::fromResponse(new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall(id: 'tooluse_1', name: 'get_weather', input: [])],
            )),
            Message::toolResults([
                new ToolResult(toolCallId: 'tooluse_1', content: 'API unavailable', isError: true),
            ]),
        ];

        $provider->completeWithMessages($messages);

        $this->assertNotNull($capturedRequest);

        /** @var array<int, array{role: string, content: array<int, array<string, mixed>>}> $msgs */
        $msgs = $capturedRequest['messages'];
        /** @var array{toolUseId: string, status: string} $toolResultBlock */
        $toolResultBlock = $msgs[2]['content'][0]['toolResult'];
        $this->assertSame('error', $toolResultBlock['status']);
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

    /**
     * Create a mock Bedrock client that captures the request for inspection.
     *
     * @param array<string, mixed> $responseData The response data to return.
     * @param array<string, mixed>|null &$capturedRequest Reference to store the captured request.
     * @return \Aws\BedrockRuntime\BedrockRuntimeClient
     */
    private function createMockBedrockClientWithCapture(array $responseData, ?array &$capturedRequest): object
    {
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

        $mockClient = $this->getMockBuilder(\Aws\BedrockRuntime\BedrockRuntimeClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['converseAsync'])
            ->getMock();

        $mockClient->method('converseAsync')
            ->willReturnCallback(function (array $request) use ($mockResult, &$capturedRequest) {
                $capturedRequest = $request;

                return new FulfilledPromise($mockResult);
            });

        return $mockClient;
    }
}
