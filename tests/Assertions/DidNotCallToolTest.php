<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\DidNotCallTool;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use LogicException;
use PHPUnit\Framework\TestCase;

class DidNotCallToolTest extends TestCase
{
    public function testPassesWhenToolWasNotCalled(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new DidNotCallTool('send_email'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testPassesWithNoToolCalls(): void
    {
        $response = new Response(
            text: 'Just text, no tool calls',
            model: 'claude-3',
            toolCalls: [],
        );

        $assertion = (new DidNotCallTool('dangerous_action'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenToolWasCalled(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'delete_everything', []),
            ],
        );

        $assertion = (new DidNotCallTool('delete_everything'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('was called 1 time(s)', $result->message);
    }

    public function testFailsWithMultipleCalls(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'send_email', ['to' => 'a@a.com']),
                new ToolCall('toolu_2', 'send_email', ['to' => 'b@b.com']),
            ],
        );

        $assertion = (new DidNotCallTool('send_email'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('was called 2 time(s)', $result->message);
    }

    public function testThrowsWhenResponseNotSet(): void
    {
        $assertion = new DidNotCallTool('get_weather');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Response not set');
        $assertion->check('');
    }

    public function testDescription(): void
    {
        $assertion = new DidNotCallTool('dangerous_action');
        $this->assertSame('Tool "dangerous_action" was not called', $assertion->getDescription());
    }

    public function testWithResponseReturnsNewInstance(): void
    {
        $response = new Response(text: '', model: 'claude-3');
        $assertion = new DidNotCallTool('get_weather');
        $newAssertion = $assertion->withResponse($response);

        $this->assertNotSame($assertion, $newAssertion);
    }
}
