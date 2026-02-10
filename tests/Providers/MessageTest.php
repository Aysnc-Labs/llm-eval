<?php

/**
 * Tests for the Message value object.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\Message;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\Role;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testUserFactoryCreatesUserMessage(): void
    {
        $message = Message::user('Hello, world!');

        $this->assertSame(Role::User, $message->role);
        $this->assertSame('Hello, world!', $message->text);
        $this->assertSame([], $message->toolCalls);
        $this->assertSame([], $message->toolResults);
    }

    public function testFromResponseCreatesAssistantMessage(): void
    {
        $response = new Response(text: 'The answer is 4.', model: 'test-model');

        $message = Message::fromResponse($response);

        $this->assertSame(Role::Assistant, $message->role);
        $this->assertSame('The answer is 4.', $message->text);
        $this->assertSame([], $message->toolCalls);
    }

    public function testFromResponsePreservesToolCalls(): void
    {
        $toolCall = new ToolCall(id: 'toolu_1', name: 'get_weather', input: ['location' => 'NYC']);
        $response = new Response(
            text: 'Let me check.',
            model: 'test-model',
            toolCalls: [$toolCall],
        );

        $message = Message::fromResponse($response);

        $this->assertSame(Role::Assistant, $message->role);
        $this->assertSame('Let me check.', $message->text);
        $this->assertCount(1, $message->toolCalls);
        $this->assertSame('get_weather', $message->toolCalls[0]->name);
    }

    public function testToolResultsFactoryCreatesToolResultMessage(): void
    {
        $results = [
            new ToolResult(toolCallId: 'toolu_1', content: '72F'),
            new ToolResult(toolCallId: 'toolu_2', content: 'Email sent'),
        ];

        $message = Message::toolResults($results);

        $this->assertSame(Role::User, $message->role);
        $this->assertSame('', $message->text);
        $this->assertCount(2, $message->toolResults);
    }

    public function testHasToolCalls(): void
    {
        $withCalls = Message::fromResponse(new Response(
            text: '',
            model: 'test',
            toolCalls: [new ToolCall(id: '1', name: 'tool')],
        ));
        $withoutCalls = Message::user('Hi');

        $this->assertTrue($withCalls->hasToolCalls());
        $this->assertFalse($withoutCalls->hasToolCalls());
    }

    public function testHasToolResults(): void
    {
        $withResults = Message::toolResults([
            new ToolResult(toolCallId: '1', content: 'ok'),
        ]);
        $withoutResults = Message::user('Hi');

        $this->assertTrue($withResults->hasToolResults());
        $this->assertFalse($withoutResults->hasToolResults());
    }

    public function testToArrayForUserMessage(): void
    {
        $message = Message::user('Hello');

        $expected = [
            'role' => 'user',
            'text' => 'Hello',
        ];

        $this->assertSame($expected, $message->toArray());
    }

    public function testToArrayForAssistantWithToolCalls(): void
    {
        $message = Message::fromResponse(new Response(
            text: 'Checking...',
            model: 'test',
            toolCalls: [
                new ToolCall(id: 'toolu_1', name: 'get_weather', input: ['city' => 'NYC']),
            ],
        ));

        $result = $message->toArray();

        $this->assertSame('assistant', $result['role']);
        $this->assertSame('Checking...', $result['text']);
        $this->assertArrayHasKey('tool_calls', $result);

        /** @var array<int, array{id: string, name: string, input: array<string, mixed>}> $toolCalls */
        $toolCalls = $result['tool_calls'];
        $this->assertSame('toolu_1', $toolCalls[0]['id']);
        $this->assertSame('get_weather', $toolCalls[0]['name']);
    }

    public function testToArrayForToolResults(): void
    {
        $message = Message::toolResults([
            new ToolResult(toolCallId: 'toolu_1', content: '72F', isError: false),
            new ToolResult(toolCallId: 'toolu_2', content: 'Error occurred', isError: true),
        ]);

        $result = $message->toArray();

        $this->assertSame('user', $result['role']);
        $this->assertArrayHasKey('tool_results', $result);

        /** @var array<int, array{tool_call_id: string, content: string, is_error: bool}> $toolResults */
        $toolResults = $result['tool_results'];
        $this->assertCount(2, $toolResults);
        $this->assertSame('toolu_1', $toolResults[0]['tool_call_id']);
        $this->assertFalse($toolResults[0]['is_error']);
        $this->assertTrue($toolResults[1]['is_error']);
    }

    public function testToArrayOmitsEmptyToolCallsAndResults(): void
    {
        $message = Message::user('Simple message');

        $result = $message->toArray();

        $this->assertArrayNotHasKey('tool_calls', $result);
        $this->assertArrayNotHasKey('tool_results', $result);
    }
}
