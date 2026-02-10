<?php

/**
 * Tests for the CallableToolExecutor.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\CallableToolExecutor;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CallableToolExecutorTest extends TestCase
{
    public function testDispatchesToCorrectCallable(): void
    {
        $executor = new CallableToolExecutor([
            'get_weather' => fn (ToolCall $tc) => new ToolResult($tc->id, '72F'),
            'get_time' => fn (ToolCall $tc) => new ToolResult($tc->id, '3:00 PM'),
        ]);

        $result = $executor->execute(new ToolCall('toolu_1', 'get_weather', ['location' => 'SF']));

        $this->assertSame('toolu_1', $result->toolCallId);
        $this->assertSame('72F', $result->content);
        $this->assertFalse($result->isError);
    }

    public function testThrowsForUnknownTool(): void
    {
        $executor = new CallableToolExecutor([
            'get_weather' => fn (ToolCall $tc) => new ToolResult($tc->id, '72F'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown tool: nonexistent_tool');

        $executor->execute(new ToolCall('toolu_1', 'nonexistent_tool'));
    }

    public function testHandlesErrorResults(): void
    {
        $executor = new CallableToolExecutor([
            'failing_tool' => fn (ToolCall $tc) => new ToolResult($tc->id, 'Something went wrong', isError: true),
        ]);

        $result = $executor->execute(new ToolCall('toolu_1', 'failing_tool'));

        $this->assertSame('toolu_1', $result->toolCallId);
        $this->assertSame('Something went wrong', $result->content);
        $this->assertTrue($result->isError);
    }

    public function testWorksWithMultipleTools(): void
    {
        $executor = new CallableToolExecutor([
            'get_weather' => fn (ToolCall $tc) => new ToolResult($tc->id, 'Sunny, 72F'),
            'get_time' => fn (ToolCall $tc) => new ToolResult($tc->id, '3:00 PM'),
            'get_news' => fn (ToolCall $tc) => new ToolResult($tc->id, 'No news today'),
        ]);

        $weather = $executor->execute(new ToolCall('toolu_1', 'get_weather', ['location' => 'SF']));
        $time = $executor->execute(new ToolCall('toolu_2', 'get_time'));
        $news = $executor->execute(new ToolCall('toolu_3', 'get_news'));

        $this->assertSame('Sunny, 72F', $weather->content);
        $this->assertSame('3:00 PM', $time->content);
        $this->assertSame('No news today', $news->content);
    }
}
