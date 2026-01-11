<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\CalledTool;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use LogicException;
use PHPUnit\Framework\TestCase;

class CalledToolTest extends TestCase
{
    public function testPassesWhenToolWasCalled(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new CalledTool('get_weather'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenToolWasNotCalled(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new CalledTool('send_email'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('was not called', $result->message);
    }

    public function testPassesWhenToolCalledExactTimes(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
                new ToolCall('toolu_2', 'get_weather', ['location' => 'London']),
            ],
        );

        $assertion = (new CalledTool('get_weather', times: 2))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenToolCalledWrongNumberOfTimes(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new CalledTool('get_weather', times: 2))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('was called 1 time(s), expected 2', $result->message);
    }

    public function testPassesWithNoToolCallsWhenTimesIsZero(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [],
        );

        $assertion = (new CalledTool('get_weather', times: 0))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testThrowsWhenResponseNotSet(): void
    {
        $assertion = new CalledTool('get_weather');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Response not set');
        $assertion->check('');
    }

    public function testDescriptionWithoutTimes(): void
    {
        $assertion = new CalledTool('get_weather');
        $this->assertSame('Tool "get_weather" was called', $assertion->getDescription());
    }

    public function testDescriptionWithTimes(): void
    {
        $assertion = new CalledTool('get_weather', times: 3);
        $this->assertSame('Tool "get_weather" called exactly 3 time(s)', $assertion->getDescription());
    }

    public function testWithResponseReturnsNewInstance(): void
    {
        $response = new Response(text: '', model: 'claude-3');
        $assertion = new CalledTool('get_weather');
        $newAssertion = $assertion->withResponse($response);

        $this->assertNotSame($assertion, $newAssertion);
    }
}
