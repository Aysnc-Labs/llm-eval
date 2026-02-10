<?php

/**
 * Tests for the ConversationEval class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Conversation;

use Aysnc\AI\LlmEval\Conversation\ConversationEval;
use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\CallableToolExecutor;
use Aysnc\AI\LlmEval\Providers\ConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConversationEvalTest extends TestCase
{
    public function testCreateConversationReturnsConversationEval(): void
    {
        $eval = LlmEval::createConversation('test');

        $this->assertInstanceOf(ConversationEval::class, $eval);
        $this->assertSame('test', $eval->getName());
    }

    public function testSimpleConversationEval(): void
    {
        $provider = $this->createScriptedProvider([
            // Row 1
            new Response(text: 'The answer is 4.', model: 'test'),
            // Row 2
            new Response(text: 'The capital is Paris.', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'What is 2+2?', 'expected' => '4'],
            ['prompt' => 'Capital of France?', 'expected' => 'Paris'],
        ]);

        $result = ConversationEval::create('simple-conv')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected();
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertTrue($result->passed);
        $this->assertSame(2, $result->totalCount());
    }

    public function testConversationEvalWithToolLoop(): void
    {
        $provider = $this->createScriptedProvider([
            // Tool call response.
            new Response(
                text: 'Let me check.',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris'])],
            ),
            // Final response after tool result.
            new Response(text: 'It is 72F in Paris.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'get_weather' => fn (ToolCall $tc) => new ToolResult($tc->id, '72F'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'What is the weather in Paris?'],
        ]);

        $result = ConversationEval::create('tool-conv')
            ->provider($provider)
            ->executor($executor)
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->contains('72F')
                    ->turnCount(2)
                    ->usedTool('get_weather');
            })
            ->runAll();

        $this->assertTrue($result->passed);
    }

    public function testTurnCountFailure(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hello!', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'Hi'],
        ]);

        $result = ConversationEval::create('turn-fail')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->turnCount(3); // Expect 3 but only 1 turn.
            })
            ->runAll();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Expected 3 turn(s), got 1', $result->results[0]->assertionResults[0]->message);
    }

    public function testUsedToolFailure(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'I can answer directly: 4', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'What is 2+2?'],
        ]);

        $result = ConversationEval::create('tool-fail')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->usedTool('calculator');
            })
            ->runAll();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('not used in any turn', $result->results[0]->assertionResults[0]->message);
    }

    public function testConversationContains(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(
                text: 'Checking...',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'lookup', [])],
            ),
            new Response(text: 'Done.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'lookup' => fn (ToolCall $tc) => new ToolResult($tc->id, 'secret data'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'Find the secret data'],
        ]);

        $result = ConversationEval::create('conv-contains')
            ->provider($provider)
            ->executor($executor)
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                // The user message contains "secret data".
                $expect->conversationContains('secret data');
            })
            ->runAll();

        $this->assertTrue($result->passed);
    }

    public function testConversationContainsFailure(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hello!', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'Hi'],
        ]);

        $result = ConversationEval::create('conv-contains-fail')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->conversationContains('nonexistent phrase');
            })
            ->runAll();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('not found in any conversation message', $result->results[0]->assertionResults[0]->message);
    }

    public function testThrowsWithoutConversableProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ConversableProviderInterface');

        ConversationEval::create('bad-provider')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset(Dataset::fromArray([['prompt' => 'Hi']]))
            ->assertions(function ($expect): void {
                $expect->contains('Hi');
            })
            ->runAll();
    }

    public function testThrowsWithoutExecutor(): void
    {
        $provider = $this->createMock(ConversableProviderInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Executor must be set');

        ConversationEval::create('no-executor')
            ->provider($provider)
            ->dataset(Dataset::fromArray([['prompt' => 'Hi']]))
            ->assertions(function ($expect): void {
                $expect->contains('Hi');
            })
            ->runAll();
    }

    public function testRunAllParallelDelegatesToRunAll(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hello!', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'Hi'],
        ]);

        $result = ConversationEval::create('parallel-fallback')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->contains('Hello');
            })
            ->runAllParallel();

        $this->assertTrue($result->passed);
    }

    public function testMixedAssertionsWorkTogether(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'calculate', ['expr' => '6*7'])],
            ),
            new Response(text: 'The answer is 42.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'calculate' => fn (ToolCall $tc) => new ToolResult($tc->id, '42'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'What is 6*7?', 'expected' => '42'],
        ]);

        $result = ConversationEval::create('mixed')
            ->provider($provider)
            ->executor($executor)
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected();
                // Standard assertion on final response.
                if ($expected !== null) {
                    $expect->contains($expected);
                }
                // Conversation-specific assertions.
                $expect->turnCount(2)
                    ->usedTool('calculate')
                    ->conversationContains('6*7');
            })
            ->runAll();

        $this->assertTrue($result->passed);
        $this->assertSame(4, $result->results[0]->totalCount());
    }

    public function testCaseNamingFromMetadata(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Result', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            ['prompt' => 'Test', 'name' => 'custom-name'],
        ]);

        $result = ConversationEval::create('naming')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->contains('Result');
            })
            ->runAll();

        $this->assertStringContainsString('custom-name', $result->results[0]->name);
    }

    public function testPerTurnAssertions(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hi there!', model: 'test'),
            new Response(text: 'I am great!', model: 'test'),
            new Response(text: 'Bye!', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            [
                'turns' => [
                    ['prompt' => 'Hello', 'expected' => 'Hi'],
                    ['prompt' => 'How are you?', 'expected' => 'great'],
                    ['prompt' => 'Goodbye', 'expected' => 'Bye'],
                ],
                'name' => 'greeting',
            ],
        ]);

        $result = ConversationEval::create('per-turn')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected();
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertTrue($result->passed);
        // 3 results: one per turn.
        $this->assertSame(3, $result->totalCount());
        $this->assertStringContainsString('Turn 1', $result->results[0]->name);
        $this->assertStringContainsString('Turn 2', $result->results[1]->name);
        $this->assertStringContainsString('Turn 3', $result->results[2]->name);
    }

    public function testPerTurnFailureIsIndependent(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Paris is 22C.', model: 'test'),
            new Response(text: 'Oops, no data.', model: 'test'), // Turn 2 fails.
            new Response(text: 'Paris is warmer.', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            [
                'turns' => [
                    ['prompt' => 'Paris weather', 'expected' => '22'],
                    ['prompt' => 'Tokyo weather', 'expected' => '18'],
                    ['prompt' => 'Which is warmer?', 'expected' => 'Paris'],
                ],
            ],
        ]);

        $result = ConversationEval::create('partial-fail')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected();
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertFalse($result->passed);
        $this->assertTrue($result->results[0]->passed);  // Turn 1: "22" in "Paris is 22C."
        $this->assertFalse($result->results[1]->passed);  // Turn 2: "18" not in "Oops, no data."
        $this->assertTrue($result->results[2]->passed);  // Turn 3: "Paris" in "Paris is warmer."
    }

    public function testStringTurnsProduceNoAssertions(): void
    {
        $provider = $this->createScriptedProvider([
            new Response(text: 'Hi!', model: 'test'),
            new Response(text: 'Good.', model: 'test'),
        ]);

        $dataset = Dataset::fromArray([
            [
                'turns' => [
                    ['prompt' => 'Hello', 'expected' => 'Hi'],
                    'How are you?', // String — no expected values.
                ],
            ],
        ]);

        $result = ConversationEval::create('string-turns')
            ->provider($provider)
            ->executor(new CallableToolExecutor([]))
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected();
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertTrue($result->passed);
        $this->assertSame(2, $result->totalCount());
        // Turn 1 has 1 assertion (contains "Hi"). Turn 2 has 0 assertions (passes trivially).
        $this->assertSame(1, $result->results[0]->totalCount());
        $this->assertSame(0, $result->results[1]->totalCount());
    }

    public function testTurnsWithToolLoop(): void
    {
        $provider = $this->createScriptedProvider([
            // send: tool call + final.
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_1', 'get_weather', ['location' => 'Paris'])],
            ),
            new Response(text: 'Paris is 22C.', model: 'test'),
            // reply: tool call + final.
            new Response(
                text: '',
                model: 'test',
                toolCalls: [new ToolCall('toolu_2', 'get_weather', ['location' => 'Tokyo'])],
            ),
            new Response(text: 'Tokyo is 18C.', model: 'test'),
        ]);

        $executor = new CallableToolExecutor([
            'get_weather' => fn (ToolCall $tc) => new ToolResult($tc->id, 'weather data'),
        ]);

        $dataset = Dataset::fromArray([
            [
                'turns' => [
                    ['prompt' => 'Weather in Paris?', 'expected' => '22'],
                    ['prompt' => 'Now check Tokyo', 'expected' => '18'],
                ],
            ],
        ]);

        $result = ConversationEval::create('turns-tools')
            ->provider($provider)
            ->executor($executor)
            ->withTools([['name' => 'get_weather']])
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected();
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertTrue($result->passed);
        $this->assertSame(2, $result->totalCount());
    }

    /**
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
