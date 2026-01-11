<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\ToolCallHasParam;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use LogicException;
use PHPUnit\Framework\TestCase;

class ToolCallHasParamTest extends TestCase
{
    public function testPassesWhenParamExists(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris', 'unit' => 'celsius']),
            ],
        );

        $assertion = (new ToolCallHasParam('get_weather', 'location'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenParamDoesNotExist(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new ToolCallHasParam('get_weather', 'unit'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('does not have parameter "unit"', $result->message);
    }

    public function testFailsWhenToolNotCalled(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new ToolCallHasParam('send_email', 'to'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('was not called', $result->message);
    }

    public function testPassesWhenParamHasExpectedValue(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new ToolCallHasParam('get_weather', 'location', 'Paris'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenParamHasWrongValue(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'London']),
            ],
        );

        $assertion = (new ToolCallHasParam('get_weather', 'location', 'Paris'))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('has value "London", expected "Paris"', $result->message);
    }

    public function testHandlesBooleanValues(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'set_setting', ['enabled' => true]),
            ],
        );

        $assertion = (new ToolCallHasParam('set_setting', 'enabled', true))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testHandlesIntegerValues(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'set_limit', ['count' => 42]),
            ],
        );

        $assertion = (new ToolCallHasParam('set_limit', 'count', 42))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testHandlesNullValues(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'clear_cache', ['scope' => null]),
            ],
        );

        $assertion = (new ToolCallHasParam('clear_cache', 'scope', null))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testHandlesArrayValues(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'send_to', ['recipients' => ['a@a.com', 'b@b.com']]),
            ],
        );

        $assertion = (new ToolCallHasParam('send_to', 'recipients', ['a@a.com', 'b@b.com']))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testThrowsWhenResponseNotSet(): void
    {
        $assertion = new ToolCallHasParam('get_weather', 'location');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Response not set');
        $assertion->check('');
    }

    public function testDescriptionWithoutValue(): void
    {
        $assertion = new ToolCallHasParam('get_weather', 'location');
        $this->assertSame('Tool "get_weather" has parameter "location"', $assertion->getDescription());
    }

    public function testDescriptionWithStringValue(): void
    {
        $assertion = new ToolCallHasParam('get_weather', 'location', 'Paris');
        $this->assertSame('Tool "get_weather" has parameter "location" = "Paris"', $assertion->getDescription());
    }

    public function testDescriptionWithBoolValue(): void
    {
        $assertion = new ToolCallHasParam('set_setting', 'enabled', true);
        $this->assertSame('Tool "set_setting" has parameter "enabled" = true', $assertion->getDescription());
    }

    public function testWithResponseReturnsNewInstance(): void
    {
        $response = new Response(text: '', model: 'claude-3');
        $assertion = new ToolCallHasParam('get_weather', 'location');
        $newAssertion = $assertion->withResponse($response);

        $this->assertNotSame($assertion, $newAssertion);
    }
}
