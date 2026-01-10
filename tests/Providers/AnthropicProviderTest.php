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
