<?php

/**
 * Tests for the LlmEval class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests;

use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Test case for LlmEval class.
 */
class LlmEvalTest extends TestCase
{
    /**
     * Test that an LlmEval can be created with a name.
     */
    public function testCreateWithName(): void
    {
        $eval = LlmEval::create('my-test');

        $this->assertSame('my-test', $eval->getName());
    }

    /**
     * Test fluent provider method.
     */
    public function testProviderReturnsSelf(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $eval = LlmEval::create('test');

        $result = $eval->provider($provider);

        $this->assertSame($eval, $result);
    }

    /**
     * Test fluent prompt method.
     */
    public function testPromptReturnsSelf(): void
    {
        $eval = LlmEval::create('test');

        $result = $eval->prompt('Hello');

        $this->assertSame($eval, $result);
        $this->assertSame('Hello', $eval->getPrompt());
    }

    /**
     * Test fluent model method.
     */
    public function testModelSetsOption(): void
    {
        $eval = LlmEval::create('test')
            ->model('claude-3-haiku-20240307');

        $this->assertSame('claude-3-haiku-20240307', $eval->getOptions()['model']);
    }

    /**
     * Test fluent maxTokens method.
     */
    public function testMaxTokensSetsOption(): void
    {
        $eval = LlmEval::create('test')
            ->maxTokens(500);

        $this->assertSame(500, $eval->getOptions()['max_tokens']);
    }

    /**
     * Test fluent option method for custom options.
     */
    public function testCustomOption(): void
    {
        $eval = LlmEval::create('test')
            ->option('temperature', 0.7);

        $this->assertSame(0.7, $eval->getOptions()['temperature']);
    }

    /**
     * Test run throws if provider not set.
     */
    public function testRunThrowsWithoutProvider(): void
    {
        $eval = LlmEval::create('test')
            ->prompt('Hello');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider must be set');

        $eval->run();
    }

    /**
     * Test run throws if prompt not set.
     */
    public function testRunThrowsWithoutPrompt(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $eval = LlmEval::create('test')
            ->provider($provider);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt must be set');

        $eval->run();
    }

    /**
     * Test successful run returns response.
     */
    public function testRunReturnsResponse(): void
    {
        $expectedResponse = new Response(
            text: 'The answer is 4.',
            model: 'claude-sonnet-4-20250514',
        );

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('complete')
            ->with('What is 2+2?', ['model' => 'claude-sonnet-4-20250514'])
            ->willReturn($expectedResponse);

        $response = LlmEval::create('math-test')
            ->provider($provider)
            ->prompt('What is 2+2?')
            ->model('claude-sonnet-4-20250514')
            ->run();

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test full fluent chain.
     */
    public function testFluentChain(): void
    {
        $response = new Response(text: 'Hi!', model: 'test-model');

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('complete')->willReturn($response);

        $result = LlmEval::create('chain-test')
            ->provider($provider)
            ->prompt('Hello')
            ->model('test-model')
            ->maxTokens(100)
            ->option('temperature', 0.5)
            ->run();

        $this->assertSame('Hi!', $result->text);
    }
}
