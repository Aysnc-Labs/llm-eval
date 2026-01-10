<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests;

use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use PHPUnit\Framework\TestCase;

class ExpectationTest extends TestCase
{
    public function testExpectReturnsExpectation(): void
    {
        $eval = LlmEval::create('test');
        $expectation = $eval->expect();

        $this->assertInstanceOf(\Aysnc\AI\LlmEval\Expectation::class, $expectation);
    }

    public function testFluentAssertionChaining(): void
    {
        $eval = LlmEval::create('test');
        $expectation = $eval->expect()
            ->contains('hello')
            ->notContains('goodbye')
            ->matchesRegex('/\w+/')
            ->isJson()
            ->maxLength(100)
            ->minLength(1);

        $this->assertCount(6, $expectation->getAssertions());
    }

    public function testRunWithAssertionsReturnsResult(): void
    {
        $response = new Response(text: '{"answer": 4}', model: 'test-model');

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')->willReturn($response);

        $result = LlmEval::create('math-test')
            ->provider($provider)
            ->prompt('What is 2+2?')
            ->expect()
                ->contains('4')
                ->isJson()
            ->run();

        $this->assertTrue($result->passed);
        $this->assertSame(2, $result->passedCount());
        $this->assertSame(0, $result->failedCount());
    }

    public function testFailedAssertionsReportedInResult(): void
    {
        $response = new Response(text: 'The answer is four', model: 'test-model');

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')->willReturn($response);

        $result = LlmEval::create('math-test')
            ->provider($provider)
            ->prompt('What is 2+2?')
            ->expect()
                ->contains('4')      // will fail
                ->contains('answer') // will pass
                ->isJson()           // will fail
            ->run();

        $this->assertFalse($result->passed);
        $this->assertSame(1, $result->passedCount());
        $this->assertSame(2, $result->failedCount());
        $this->assertCount(2, $result->getFailures());
    }

    public function testResultContainsResponseData(): void
    {
        $response = new Response(
            text: 'Hello!',
            model: 'claude-sonnet-4-20250514',
            inputTokens: 10,
            outputTokens: 5,
        );

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')->willReturn($response);

        $result = LlmEval::create('greeting-test')
            ->provider($provider)
            ->prompt('Say hi')
            ->expect()
                ->contains('Hello')
            ->run();

        $this->assertSame('greeting-test', $result->name);
        $this->assertSame('Hello!', $result->response->text);
        $this->assertSame('claude-sonnet-4-20250514', $result->response->model);
        $this->assertSame(15, $result->response->totalTokens());
    }
}
