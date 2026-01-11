<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\CalledToolCount;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use LogicException;
use PHPUnit\Framework\TestCase;

class CalledToolCountTest extends TestCase
{
    public function testPassesWithCorrectCount(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
                new ToolCall('toolu_2', 'send_email', ['to' => 'test@test.com']),
            ],
        );

        $assertion = (new CalledToolCount(2))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testPassesWithZeroToolCalls(): void
    {
        $response = new Response(
            text: 'Just text',
            model: 'claude-3',
            toolCalls: [],
        );

        $assertion = (new CalledToolCount(0))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testFailsWithWrongCount(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
            ],
        );

        $assertion = (new CalledToolCount(3))->withResponse($response);
        $result = $assertion->check('');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('has 1 tool call(s), expected 3', $result->message);
    }

    public function testCountsMultipleToolsOfSameName(): void
    {
        $response = new Response(
            text: '',
            model: 'claude-3',
            toolCalls: [
                new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
                new ToolCall('toolu_2', 'get_weather', ['location' => 'London']),
                new ToolCall('toolu_3', 'get_weather', ['location' => 'Berlin']),
            ],
        );

        $assertion = (new CalledToolCount(3))->withResponse($response);
        $result = $assertion->check('');

        $this->assertTrue($result->passed);
    }

    public function testThrowsWhenResponseNotSet(): void
    {
        $assertion = new CalledToolCount(1);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Response not set');
        $assertion->check('');
    }

    public function testDescription(): void
    {
        $assertion = new CalledToolCount(5);
        $this->assertSame('Response has exactly 5 tool call(s)', $assertion->getDescription());
    }

    public function testWithResponseReturnsNewInstance(): void
    {
        $response = new Response(text: '', model: 'claude-3');
        $assertion = new CalledToolCount(1);
        $newAssertion = $assertion->withResponse($response);

        $this->assertNotSame($assertion, $newAssertion);
    }
}
