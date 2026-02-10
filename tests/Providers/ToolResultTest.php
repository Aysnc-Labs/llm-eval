<?php

/**
 * Tests for the ToolResult value object.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\ToolResult;
use PHPUnit\Framework\TestCase;

class ToolResultTest extends TestCase
{
    public function testCreatesToolResult(): void
    {
        $result = new ToolResult(
            toolCallId: 'toolu_123',
            content: '{"temperature": 72}',
        );

        $this->assertSame('toolu_123', $result->toolCallId);
        $this->assertSame('{"temperature": 72}', $result->content);
        $this->assertFalse($result->isError);
    }

    public function testCreatesErrorToolResult(): void
    {
        $result = new ToolResult(
            toolCallId: 'toolu_456',
            content: 'Tool not found',
            isError: true,
        );

        $this->assertSame('toolu_456', $result->toolCallId);
        $this->assertSame('Tool not found', $result->content);
        $this->assertTrue($result->isError);
    }

    public function testDefaultIsNotError(): void
    {
        $result = new ToolResult(toolCallId: 'id', content: 'ok');

        $this->assertFalse($result->isError);
    }
}
