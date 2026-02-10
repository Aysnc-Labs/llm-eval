<?php

/**
 * Tests for the Conversation class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Conversation;

use Aysnc\AI\LlmEval\Conversation\Conversation;
use Aysnc\AI\LlmEval\Providers\CallableToolExecutor;
use Aysnc\AI\LlmEval\Providers\ConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\Message;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\Role;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConversationTest extends TestCase
{
    public function testSingleTurnNoTools(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hello!', model: 'test'),
        ]);
        $executor = new CallableToolExecutor([]);

        $conversation = Conversation::make($provider, $executor);
        $response = $conversation->send('Hi');

        $this->assertSame('Hello!', $response->text);
        $this->assertCount(1, $conversation->getResponses());
        $this->assertCount(2, $conversation->getMessages()); // user + assistant
        $this->assertSame(Role::User, $conversation->getMessages()[0]->role);
        $this->assertSame(Role::Assistant, $conversation->getMessages()[1]->role);
    }

    public function testToolLoopExecutesToolsAndSendsResults(): void
    {
        $provider = $this->createScriptedProvider([
            // First response: LLM calls a tool.
            new Response(
                text: 'Let me check the weather.',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris'])],
            ),
            // Second response: LLM gives final answer after tool result.
            new Response(text: 'It is 72F in Paris.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'get_weather' => fn (ToolCall $tc) => new ToolResult($tc->id, '72F'),
        ]);

        $conversation = Conversation::make($provider, $executor);
        $response = $conversation->send('What is the weather in Paris?');

        $this->assertSame('It is 72F in Paris.', $response->text);
        $this->assertCount(2, $conversation->getResponses());

        // Messages: user, assistant (tool call), tool results, assistant (final).
        $messages = $conversation->getMessages();
        $this->assertCount(4, $messages);
        $this->assertSame(Role::User, $messages[0]->role);
        $this->assertTrue($messages[1]->hasToolCalls());
        $this->assertTrue($messages[2]->hasToolResults());
        $this->assertSame('It is 72F in Paris.', $messages[3]->text);
    }

    public function testMultipleToolCallsInSingleResponse(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(
                text: 'Checking both...',
                model: 'test',
                toolCalls: [
                    new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris']),
                    new ToolCall('toolu_2', 'get_weather', ['location' => 'Tokyo']),
                ],
            ),
            new Response(text: 'Paris is 72F, Tokyo is 80F.', model: 'test'),
        ]);

        /** @var array<string> $executedCalls */
        $executedCalls = [];
        $executor = new CallableToolExecutor([
            'get_weather' => function (ToolCall $tc) use (&$executedCalls) {
                /** @var string $location */
                $location = $tc->getParam('location');
                $executedCalls[] = $location;
                $temps = ['Paris' => '72F', 'Tokyo' => '80F'];

                return new ToolResult($tc->id, $temps[$location] ?? 'unknown');
            },
        ]);

        $conversation = Conversation::make($provider, $executor);
        $response = $conversation->send('Weather in Paris and Tokyo?');

        $this->assertSame('Paris is 72F, Tokyo is 80F.', $response->text);
        $this->assertSame(['Paris', 'Tokyo'], $executedCalls);

        // The tool results message should contain both results.
        $toolResultsMsg = $conversation->getMessages()[2];
        $this->assertCount(2, $toolResultsMsg->toolResults);
    }

    public function testMultipleTurnToolLoop(): void
    {
        $provider = $this->createScriptedProvider([
            // Turn 1: call tool A.
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'search', ['query' => 'PHP'])],
            ),
            // Turn 2: call tool B based on search result.
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_2', 'fetch', ['url' => 'php.net'])],
            ),
            // Turn 3: final answer.
            new Response(text: 'PHP is a scripting language.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'search' => fn (ToolCall $tc) => new ToolResult($tc->id, 'php.net'),
            'fetch' => fn (ToolCall $tc) => new ToolResult($tc->id, 'PHP is a scripting language.'),
        ]);

        $conversation = Conversation::make($provider, $executor);
        $response = $conversation->send('Tell me about PHP');

        $this->assertSame('PHP is a scripting language.', $response->text);
        $this->assertCount(3, $conversation->getResponses());
    }

    public function testMaxTurnsThrowsException(): void
    {
        // Provider always returns tool calls — infinite loop without max turns.
        $provider = $this->createScriptedProvider([
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'loop', [])],
            ),
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_2', 'loop', [])],
            ),
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_3', 'loop', [])],
            ),
        ]);

        $executor = new CallableToolExecutor([
            'loop' => fn (ToolCall $tc) => new ToolResult($tc->id, 'looping'),
        ]);

        $conversation = Conversation::make($provider, $executor)->withMaxTurns(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conversation exceeded max turns (2)');

        $conversation->send('Start loop');
    }

    public function testReplyAppendsToHistory(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hello!', model: 'test'),
            new Response(text: 'I am fine.', model: 'test'),
        ]);
        $executor = new CallableToolExecutor([]);

        $conversation = Conversation::make($provider, $executor);
        $conversation->send('Hi');
        $response = $conversation->reply('How are you?');

        $this->assertSame('I am fine.', $response->text);
        $this->assertCount(2, $conversation->getResponses());

        // Messages: user, assistant, user, assistant.
        $messages = $conversation->getMessages();
        $this->assertCount(4, $messages);
        $this->assertSame('Hi', $messages[0]->text);
        $this->assertSame('Hello!', $messages[1]->text);
        $this->assertSame('How are you?', $messages[2]->text);
        $this->assertSame('I am fine.', $messages[3]->text);
    }

    public function testReplyWithToolLoop(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hello!', model: 'test'),
            // Reply triggers a tool call.
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'get_time', [])],
            ),
            new Response(text: 'It is 3:00 PM.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'get_time' => fn (ToolCall $tc) => new ToolResult($tc->id, '3:00 PM'),
        ]);

        $conversation = Conversation::make($provider, $executor);
        $conversation->send('Hi');
        $response = $conversation->reply('What time is it?');

        $this->assertSame('It is 3:00 PM.', $response->text);
        $this->assertCount(3, $conversation->getResponses());
    }

    public function testSendResetsHistory(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'First conversation.', model: 'test'),
            new Response(text: 'New conversation.', model: 'test'),
        ]);
        $executor = new CallableToolExecutor([]);

        $conversation = Conversation::make($provider, $executor);
        $conversation->send('Hello');

        $this->assertCount(2, $conversation->getMessages());

        // send() resets history.
        $conversation->send('Start over');

        $this->assertCount(2, $conversation->getMessages());
        $this->assertSame('Start over', $conversation->getMessages()[0]->text);
    }

    public function testWithToolsPassesToolsToProvider(): void
    {
        $capturedOptions = [];
        $provider = $this->createMock(ConversableProviderInterface::class);
        $provider->method('completeWithMessages')
            ->willReturnCallback(function (array $messages, array $options) use (&$capturedOptions) {
                $capturedOptions = $options;

                return new Response(text: 'Done.', model: 'test');
            });

        $tools = [
            ['name' => 'get_weather', 'description' => 'Get weather', 'input_schema' => ['type' => 'object']],
        ];

        $executor = new CallableToolExecutor([]);

        Conversation::make($provider, $executor)
            ->withTools($tools)
            ->withMaxTokens(2048)
            ->send('Test');

        $this->assertSame($tools, $capturedOptions['tools']);
        $this->assertSame(2048, $capturedOptions['max_tokens']);
    }

    public function testErrorToolResultFlowsThrough(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'failing', [])],
            ),
            new Response(text: 'Sorry, the tool failed.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'failing' => fn (ToolCall $tc) => new ToolResult($tc->id, 'Connection error', isError: true),
        ]);

        $conversation = Conversation::make($provider, $executor);
        $response = $conversation->send('Do the thing');

        $this->assertSame('Sorry, the tool failed.', $response->text);

        // Verify the error result was sent back.
        $toolResultsMsg = $conversation->getMessages()[2];
        $this->assertTrue($toolResultsMsg->toolResults[0]->isError);
    }

    /**
     * Create a mock provider that returns responses in order.
     *
     * @param array<Response> $responses
     */
    private function createScriptedProvider(array $responses): ConversableProviderInterface
    {
        $provider = $this->createMock(ConversableProviderInterface::class);
        $provider->method('completeWithMessages')
            ->willReturnOnConsecutiveCalls(...array_values($responses));

        return $provider;
    }
}
