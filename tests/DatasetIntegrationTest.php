<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests;

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use PHPUnit\Framework\TestCase;

class DatasetIntegrationTest extends TestCase
{
    public function testRunAllWithDataset(): void
    {
        // Create a provider that echoes back a simple response
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')
            ->willReturnCallback(function (string $prompt): Response {
                // Simple mock: if prompt contains "2+2", respond with "4"
                if (str_contains($prompt, '2+2')) {
                    return new Response(text: 'The answer is 4', model: 'test');
                }
                if (str_contains($prompt, '3+3')) {
                    return new Response(text: 'The answer is 6', model: 'test');
                }

                return new Response(text: 'Unknown', model: 'test');
            });

        $dataset = Dataset::fromArray([
            ['prompt' => 'What is 2+2?', 'expected' => '4'],
            ['prompt' => 'What is 3+3?', 'expected' => '6'],
        ]);

        $suiteResult = LlmEval::create('math-suite')
            ->provider($provider)
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected('default');
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertTrue($suiteResult->passed);
        $this->assertSame(2, $suiteResult->totalCount());
        $this->assertSame(2, $suiteResult->passedCount());
        $this->assertSame('100.0%', $suiteResult->passRatePercent());
    }

    public function testRunAllWithPartialFailures(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')
            ->willReturn(new Response(text: 'Always returns 4', model: 'test'));

        $dataset = Dataset::fromArray([
            ['prompt' => 'Test 1', 'expected' => '4'],  // Will pass
            ['prompt' => 'Test 2', 'expected' => '5'],  // Will fail
        ]);

        $suiteResult = LlmEval::create('partial-suite')
            ->provider($provider)
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expected = $testCase->getExpected('default');
                if ($expected !== null) {
                    $expect->contains($expected);
                }
            })
            ->runAll();

        $this->assertFalse($suiteResult->passed);
        $this->assertSame(1, $suiteResult->passedCount());
        $this->assertSame(1, $suiteResult->failedCount());
        $this->assertSame('50.0%', $suiteResult->passRatePercent());
    }

    public function testRunAllWithMultipleAssertions(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')
            ->willReturn(new Response(text: '{"answer": 42}', model: 'test'));

        $dataset = Dataset::fromArray([
            ['prompt' => 'Give me JSON', 'expected_answer' => '42'],
        ]);

        $suiteResult = LlmEval::create('multi-assertion')
            ->provider($provider)
            ->dataset($dataset)
            ->assertions(function ($expect, $testCase): void {
                $expect
                    ->isJson()
                    ->contains($testCase->getExpected('answer') ?? '');
            })
            ->runAll();

        $this->assertTrue($suiteResult->passed);

        // Check that both assertions were run
        $result = $suiteResult->results[0];
        $this->assertSame(2, $result->totalCount());
    }
}
