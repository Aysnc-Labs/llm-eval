<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests;

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\Dataset\TestCase as EvalTestCase;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\AsyncProviderInterface;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParallelExecutionTest extends TestCase
{
    public function testRunAllParallelExecutesConcurrently(): void
    {
        $callOrder = [];

        $provider = $this->createAsyncProvider(function (string $prompt) use (&$callOrder): Response {
            $callOrder[] = $prompt;

            return new Response(
                text: "Answer to: {$prompt}",
                model: 'test',
            );
        });

        $dataset = Dataset::fromArray([
            ['prompt' => 'What is 2+2?', 'expected' => '4'],
            ['prompt' => 'What is 3+3?', 'expected' => '6'],
            ['prompt' => 'What is 4+4?', 'expected' => '8'],
        ]);

        $result = LlmEval::create('parallel-test')
            ->provider($provider)
            ->dataset($dataset)
            ->assertions(function ($expect, EvalTestCase $case): void {
                $expected = $case->getExpected();
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAllParallel();

        // All three prompts should have been called
        $this->assertCount(3, $callOrder);
        $this->assertSame(3, $result->totalCount());
    }

    public function testRunAllParallelWithConcurrencyLimit(): void
    {
        $provider = $this->createAsyncProvider(fn () => new Response(text: 'test', model: 'test'));

        $dataset = Dataset::fromArray([
            ['prompt' => 'prompt-1'],
            ['prompt' => 'prompt-2'],
            ['prompt' => 'prompt-3'],
            ['prompt' => 'prompt-4'],
            ['prompt' => 'prompt-5'],
        ]);

        $result = LlmEval::create('concurrent-test')
            ->provider($provider)
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->contains('test');
            })
            ->runAllParallel(concurrency: 2);

        // All 5 items should be processed
        $this->assertSame(5, $result->totalCount());
        $this->assertTrue($result->passed);
    }

    public function testRunAllParallelRequiresAsyncProvider(): void
    {
        $syncProvider = $this->createMock(ProviderInterface::class);

        $dataset = Dataset::fromArray([
            ['prompt' => 'test'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AsyncProviderInterface');

        LlmEval::create('test')
            ->provider($syncProvider)
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->contains('test');
            })
            ->runAllParallel();
    }

    public function testRunAllParallelRequiresDataset(): void
    {
        $provider = $this->createMock(AsyncProviderInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dataset must be set');

        LlmEval::create('test')
            ->provider($provider)
            ->assertions(function ($expect): void {
                $expect->contains('test');
            })
            ->runAllParallel();
    }

    public function testRunAllParallelRequiresAssertionBuilder(): void
    {
        $provider = $this->createMock(AsyncProviderInterface::class);

        $dataset = Dataset::fromArray([
            ['prompt' => 'test'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Assertion builder must be set');

        LlmEval::create('test')
            ->provider($provider)
            ->dataset($dataset)
            ->runAllParallel();
    }

    public function testRunAllParallelIncludesTestCaseNameInResults(): void
    {
        $provider = $this->createAsyncProvider(fn () => new Response(text: 'answer', model: 'test'));

        $dataset = Dataset::fromArray([
            ['prompt' => 'test', 'name' => 'my-custom-name'],
        ]);

        $result = LlmEval::create('named-test')
            ->provider($provider)
            ->dataset($dataset)
            ->assertions(function ($expect): void {
                $expect->contains('answer');
            })
            ->runAllParallel();

        $this->assertStringContainsString('my-custom-name', $result->results[0]->name);
    }

    public function testRunAllParallelPassesOptionsToProvider(): void
    {
        $capturedOptions = [];

        $provider = $this->createMock(AsyncProviderInterface::class);
        $provider->method('completeAsync')
            ->willReturnCallback(function (string $prompt, array $options) use (&$capturedOptions): PromiseInterface {
                $capturedOptions = $options;

                return new FulfilledPromise(new Response(text: 'test', model: 'test'));
            });

        $dataset = Dataset::fromArray([
            ['prompt' => 'test'],
        ]);

        LlmEval::create('test')
            ->provider($provider)
            ->dataset($dataset)
            ->model('claude-opus-4-20250514')
            ->maxTokens(500)
            ->assertions(function ($expect): void {
                $expect->contains('test');
            })
            ->runAllParallel();

        $this->assertSame('claude-opus-4-20250514', $capturedOptions['model']);
        $this->assertSame(500, $capturedOptions['max_tokens']);
    }

    /**
     * Create an async provider mock with a custom callback for completeAsync.
     *
     * @param callable(string): Response $callback
     */
    private function createAsyncProvider(callable $callback): AsyncProviderInterface
    {
        $provider = $this->createMock(AsyncProviderInterface::class);
        $provider->method('completeAsync')
            ->willReturnCallback(function (string $prompt) use ($callback): PromiseInterface {
                return new FulfilledPromise($callback($prompt));
            });

        return $provider;
    }
}
