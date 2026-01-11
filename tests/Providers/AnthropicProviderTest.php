<?php

/**
 * Tests for the AnthropicProvider class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\AnthropicProvider;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
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
