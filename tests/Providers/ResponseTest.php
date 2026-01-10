<?php

/**
 * Tests for the Response class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\Response;
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
    }
}
