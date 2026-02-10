<?php

/**
 * Tests for the AnthropicProvider class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\AnthropicProvider;
use Aysnc\AI\LlmEval\Providers\Message;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;

/**
 * Test case for AnthropicProvider.
 */
class AnthropicProviderTest extends TestCase
{
    /**
     * Test that complete returns a Response with text.
     */
    public function testCompleteReturnsResponse(): void
    {
        $mockResponse = [
            'id' => 'msg_123',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'The answer is 4.'],
            ],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
            ],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

        $response = $provider->complete('What is 2+2?');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('The answer is 4.', $response->text);
        $this->assertSame('claude-sonnet-4-20250514', $response->model);
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
            'model' => 'claude-3-haiku-20240307',
            'content' => [
                ['type' => 'text', 'text' => 'Hello!'],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

        $response = $provider->complete('Hi', ['model' => 'claude-3-haiku-20240307']);

        $this->assertSame('claude-3-haiku-20240307', $response->model);
    }

    /**
     * Test handling of empty content array.
     */
    public function testCompleteWithEmptyContent(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 0],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

        $response = $provider->complete('Hi');

        $this->assertSame('', $response->text);
    }

    /**
     * Test that tool calls are extracted from response.
     */
    public function testCompleteExtractsToolCalls(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01A09q90qw90lq917835lqub',
                    'name' => 'get_weather',
                    'input' => ['location' => 'San Francisco', 'unit' => 'celsius'],
                ],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

        $response = $provider->complete('What is the weather?');

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);

        $toolCall = $response->toolCalls[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('toolu_01A09q90qw90lq917835lqub', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['location' => 'San Francisco', 'unit' => 'celsius'], $toolCall->input);
    }

    /**
     * Test that multiple tool calls are extracted.
     */
    public function testCompleteExtractsMultipleToolCalls(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_1',
                    'name' => 'get_weather',
                    'input' => ['location' => 'NYC'],
                ],
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_2',
                    'name' => 'send_email',
                    'input' => ['to' => 'test@example.com', 'body' => 'Hello'],
                ],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

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
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Let me check the weather for you.'],
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_1',
                    'name' => 'get_weather',
                    'input' => ['location' => 'NYC'],
                ],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 15],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

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
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Hello!'],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

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
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_1',
                    'name' => 'get_current_time',
                    'input' => [],
                ],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

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
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'The answer is 4.'],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

        $response = $provider->completeWithMessages([Message::user('What is 2+2?')]);

        $this->assertSame('The answer is 4.', $response->text);
    }

    /**
     * Test completeWithMessages with multi-turn conversation.
     */
    public function testCompleteWithMessagesMultiTurn(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Paris is the capital of France.'],
            ],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ];

        $mock = new MockHandler([
            new GuzzleResponse(200, [], (string) json_encode($mockResponse)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new AnthropicProvider('test-api-key', $client);

        $messages = [
            Message::user('What is the capital of France?'),
            Message::fromResponse(new Response(text: 'The capital is Paris.', model: 'test')),
            Message::user('Are you sure?'),
        ];

        $response = $provider->completeWithMessages($messages);

        $this->assertSame('Paris is the capital of France.', $response->text);

        // Verify the request was built with the correct history
        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);

        /** @var array{messages: array<int, array{role: string, content: mixed}>} $body */
        $body = json_decode($lastRequest->getBody()->getContents(), true);
        $this->assertCount(3, $body['messages']);
        $this->assertSame('user', $body['messages'][0]['role']);
        $this->assertSame('assistant', $body['messages'][1]['role']);
        $this->assertSame('user', $body['messages'][2]['role']);
    }

    /**
     * Test completeWithMessages with tool result messages.
     */
    public function testCompleteWithMessagesToolResults(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'The weather in NYC is 72F.'],
            ],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15],
        ];

        $mock = new MockHandler([
            new GuzzleResponse(200, [], (string) json_encode($mockResponse)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new AnthropicProvider('test-api-key', $client);

        $messages = [
            Message::user('What is the weather in NYC?'),
            Message::fromResponse(new Response(
                text: 'Let me check.',
                model: 'test',
                toolCalls: [new ToolCall(id: 'toolu_1', name: 'get_weather', input: ['location' => 'NYC'])],
            )),
            Message::toolResults([
                new ToolResult(toolCallId: 'toolu_1', content: '72F and sunny'),
            ]),
        ];

        $response = $provider->completeWithMessages($messages);

        $this->assertSame('The weather in NYC is 72F.', $response->text);

        // Verify request format
        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);

        /** @var array{messages: array<int, array{role: string, content: mixed}>} $body */
        $body = json_decode($lastRequest->getBody()->getContents(), true);
        $this->assertCount(3, $body['messages']);

        // First message: user text
        $this->assertSame('user', $body['messages'][0]['role']);
        $this->assertSame('What is the weather in NYC?', $body['messages'][0]['content']);

        // Second message: assistant with tool_use blocks
        $this->assertSame('assistant', $body['messages'][1]['role']);
        /** @var array<int, array<string, mixed>> $assistantContent */
        $assistantContent = $body['messages'][1]['content'];
        $this->assertSame('text', $assistantContent[0]['type']);
        $this->assertSame('tool_use', $assistantContent[1]['type']);
        $this->assertSame('toolu_1', $assistantContent[1]['id']);

        // Third message: user with tool_result blocks
        $this->assertSame('user', $body['messages'][2]['role']);
        /** @var array<int, array<string, mixed>> $toolResultContent */
        $toolResultContent = $body['messages'][2]['content'];
        $this->assertSame('tool_result', $toolResultContent[0]['type']);
        $this->assertSame('toolu_1', $toolResultContent[0]['tool_use_id']);
        $this->assertSame('72F and sunny', $toolResultContent[0]['content']);
    }

    /**
     * Test that tool result errors include is_error flag.
     */
    public function testCompleteWithMessagesToolResultError(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Sorry, the tool failed.'],
            ],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ];

        $mock = new MockHandler([
            new GuzzleResponse(200, [], (string) json_encode($mockResponse)),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new AnthropicProvider('test-api-key', $client);

        $messages = [
            Message::user('Check the weather'),
            Message::fromResponse(new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall(id: 'toolu_1', name: 'get_weather', input: [])],
            )),
            Message::toolResults([
                new ToolResult(toolCallId: 'toolu_1', content: 'API unavailable', isError: true),
            ]),
        ];

        $provider->completeWithMessages($messages);

        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);

        /** @var array{messages: array<int, array{role: string, content: mixed}>} $body */
        $body = json_decode($lastRequest->getBody()->getContents(), true);

        /** @var array<int, array<string, mixed>> $errorContent */
        $errorContent = $body['messages'][2]['content'];
        $this->assertTrue($errorContent[0]['is_error']);
    }

    /**
     * Test that completeAsync delegates to completeWithMessagesAsync.
     */
    public function testCompleteAsyncDelegatesToMessages(): void
    {
        $mockResponse = [
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Delegated response'],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
        ];

        $client = $this->createMockClient($mockResponse);
        $provider = new AnthropicProvider('test-api-key', $client);

        // completeAsync should work the same as before (it delegates internally)
        $response = $provider->completeAsync('Test prompt')->wait();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('Delegated response', $response->text);
    }

    /**
     * Create a mock Guzzle client with a predefined response.
     *
     * @param array<string, mixed> $responseData The response data to return.
     */
    private function createMockClient(array $responseData): Client
    {
        $json = json_encode($responseData);
        assert(is_string($json));

        $mock = new MockHandler([
            new GuzzleResponse(200, [], $json),
        ]);

        $handlerStack = HandlerStack::create($mock);

        return new Client(['handler' => $handlerStack]);
    }
}
