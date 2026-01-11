<?php

/**
 * Tests for the Response class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Response value object.
 */
class ResponseTest extends TestCase
{
    /**
     * Test that Response stores all values correctly.
     */
    public function testResponseStoresValues(): void
    {
        $response = new Response(
            text: 'Hello world',
            model: 'claude-sonnet-4-20250514',
            inputTokens: 10,
            outputTokens: 20,
            raw: ['key' => 'value'],
        );

        $this->assertSame('Hello world', $response->text);
        $this->assertSame('claude-sonnet-4-20250514', $response->model);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(20, $response->outputTokens);
        $this->assertSame(['key' => 'value'], $response->raw);
    }

    /**
     * Test that totalTokens returns sum of input and output.
     */
    public function testTotalTokens(): void
    {
        $response = new Response(
            text: 'Test',
            model: 'test-model',
            inputTokens: 100,
            outputTokens: 50,
        );

        $this->assertSame(150, $response->totalTokens());
    }

    /**
     * Test default values for optional parameters.
     */
    public function testDefaultValues(): void
    {
        $response = new Response(
            text: 'Test',
            model: 'test-model',
        );

        $this->assertSame(0, $response->inputTokens);
        $this->assertSame(0, $response->outputTokens);
        $this->assertSame([], $response->raw);
        $this->assertSame([], $response->toolCalls);
    }

    /**
     * Test hasToolCalls returns false when no tool calls.
     */
    public function testHasToolCallsReturnsFalse(): void
    {
        $response = new Response(text: 'Test', model: 'test-model');

        $this->assertFalse($response->hasToolCalls());
    }

    /**
     * Test hasToolCalls returns true when tool calls exist.
     */
    public function testHasToolCallsReturnsTrue(): void
    {
        $response = new Response(
            text: '',
            model: 'test-model',
            toolCalls: [
                new ToolCall('toolu_123', 'get_weather', ['location' => 'NYC']),
            ],
        );

        $this->assertTrue($response->hasToolCalls());
    }

    /**
     * Test getToolCall returns first matching tool call.
     */
    public function testGetToolCallReturnsMatch(): void
    {
        $toolCall = new ToolCall('toolu_123', 'get_weather', ['location' => 'NYC']);
        $response = new Response(
            text: '',
            model: 'test-model',
            toolCalls: [$toolCall],
        );

        $result = $response->getToolCall('get_weather');

        $this->assertSame($toolCall, $result);
    }

    /**
     * Test getToolCall returns null when no match.
     */
    public function testGetToolCallReturnsNull(): void
    {
        $response = new Response(
            text: '',
            model: 'test-model',
            toolCalls: [
                new ToolCall('toolu_123', 'get_weather', []),
            ],
        );

        $this->assertNull($response->getToolCall('send_email'));
    }

    /**
     * Test getToolCall returns first match when multiple exist.
     */
    public function testGetToolCallReturnsFirstMatch(): void
    {
        $first = new ToolCall('toolu_1', 'get_weather', ['location' => 'NYC']);
        $second = new ToolCall('toolu_2', 'get_weather', ['location' => 'LA']);

        $response = new Response(
            text: '',
            model: 'test-model',
            toolCalls: [$first, $second],
        );

        $this->assertSame($first, $response->getToolCall('get_weather'));
    }

    /**
     * Test getToolCalls returns all matching tool calls.
     */
    public function testGetToolCallsReturnsAllMatches(): void
    {
        $weather1 = new ToolCall('toolu_1', 'get_weather', ['location' => 'NYC']);
        $email = new ToolCall('toolu_2', 'send_email', ['to' => 'test@example.com']);
        $weather2 = new ToolCall('toolu_3', 'get_weather', ['location' => 'LA']);

        $response = new Response(
            text: '',
            model: 'test-model',
            toolCalls: [$weather1, $email, $weather2],
        );

        $results = $response->getToolCalls('get_weather');

        $this->assertCount(2, $results);
        $this->assertSame($weather1, $results[0]);
        $this->assertSame($weather2, $results[1]);
    }

    /**
     * Test getToolCalls returns empty array when no matches.
     */
    public function testGetToolCallsReturnsEmptyArray(): void
    {
        $response = new Response(
            text: '',
            model: 'test-model',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', []),
            ],
        );

        $this->assertSame([], $response->getToolCalls('send_email'));
    }
}
