<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests;

use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use PHPUnit\Framework\TestCase;

class JudgedByIntegrationTest extends TestCase
{
    public function testJudgedByInFluentApi(): void
    {
        // The LLM being evaluated
        $llm = $this->createMock(ProviderInterface::class);
        $llm->method('complete')
            ->willReturn(new Response(
                text: 'Quantum computing uses qubits, which can be both 0 and 1 at the same time, like magic!',
                model: 'test-llm',
            ));

        // The judge LLM
        $judge = $this->createMock(ProviderInterface::class);
        $judge->method('complete')
            ->willReturn(new Response(
                text: '{"pass": true, "score": 0.85, "reasoning": "Age-appropriate explanation"}',
                model: 'judge',
            ));

        $result = LlmEval::create('explain-to-child')
            ->provider($llm)
            ->prompt('Explain quantum computing to a 5 year old')
            ->expect()
                ->judgedBy($judge, 'Is this explanation age-appropriate and easy to understand?')
            ->run();

        $this->assertTrue($result->passed);
    }

    public function testMultipleJudgments(): void
    {
        $llm = $this->createMock(ProviderInterface::class);
        $llm->method('complete')
            ->willReturn(new Response(text: 'Test response', model: 'test'));

        // Create judges with different responses
        $helpfulJudge = $this->createJudge(true, 0.9, 'Very helpful');
        $accurateJudge = $this->createJudge(true, 0.8, 'Factually accurate');
        $toneJudge = $this->createJudge(false, 0.4, 'Tone is too casual');

        $result = LlmEval::create('multi-judge')
            ->provider($llm)
            ->prompt('Some prompt')
            ->expect()
                ->judgedBy($helpfulJudge, 'Is this helpful?')
                ->judgedBy($accurateJudge, 'Is this accurate?')
                ->judgedBy($toneJudge, 'Is the tone appropriate?', threshold: 0.6)
            ->run();

        $this->assertFalse($result->passed);
        $this->assertSame(2, $result->passedCount());
        $this->assertSame(1, $result->failedCount());
    }

    public function testCombineJudgmentWithRegularAssertions(): void
    {
        $llm = $this->createMock(ProviderInterface::class);
        $llm->method('complete')
            ->willReturn(new Response(text: '{"answer": 4}', model: 'test'));

        $judge = $this->createJudge(true, 0.95, 'Correct format');

        $result = LlmEval::create('mixed-assertions')
            ->provider($llm)
            ->prompt('What is 2+2? Reply as JSON.')
            ->expect()
                ->isJson()
                ->contains('4')
                ->judgedBy($judge, 'Is the response well-formatted?')
            ->run();

        $this->assertTrue($result->passed);
        $this->assertSame(3, $result->totalCount());
    }

    private function createJudge(bool $pass, float $score, string $reasoning): ProviderInterface
    {
        $mock = $this->createMock(ProviderInterface::class);
        $mock->method('complete')
            ->willReturn(new Response(
                text: sprintf('{"pass": %s, "score": %.2f, "reasoning": "%s"}', $pass ? 'true' : 'false', $score, $reasoning),
                model: 'judge',
            ));

        return $mock;
    }
}
