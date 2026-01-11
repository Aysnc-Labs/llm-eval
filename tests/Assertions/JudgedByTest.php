<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\JudgedBy;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use PHPUnit\Framework\TestCase;

class JudgedByTest extends TestCase
{
    public function testPassesWhenJudgeApproves(): void
    {
        $judge = $this->createMockJudge('{"pass": true, "score": 0.9, "reasoning": "Looks good"}');

        $assertion = new JudgedBy($judge, 'Is this helpful?');
        $result = $assertion->check('This is a helpful response.');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenJudgeRejects(): void
    {
        $judge = $this->createMockJudge('{"pass": false, "score": 0.3, "reasoning": "Not helpful"}');

        $assertion = new JudgedBy($judge, 'Is this helpful?');
        $result = $assertion->check('Unhelpful response.');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('30%', $result->message);
        $this->assertStringContainsString('Not helpful', $result->message);
    }

    public function testFailsWhenScoreBelowThreshold(): void
    {
        $judge = $this->createMockJudge('{"pass": true, "score": 0.5, "reasoning": "Okay"}');

        $assertion = new JudgedBy($judge, 'Is this helpful?', threshold: 0.8);
        $result = $assertion->check('Mediocre response.');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('50%', $result->message);
        $this->assertStringContainsString('threshold: 80%', $result->message);
        $this->assertStringContainsString('Okay', $result->message);
    }

    public function testPassesWhenScoreAtThreshold(): void
    {
        $judge = $this->createMockJudge('{"pass": true, "score": 0.7, "reasoning": "Good enough"}');

        $assertion = new JudgedBy($judge, 'Is this helpful?', threshold: 0.7);
        $result = $assertion->check('Decent response.');

        $this->assertTrue($result->passed);
    }

    public function testExtractsJsonFromExtraText(): void
    {
        $judge = $this->createMockJudge('Here is my evaluation: {"pass": true, "score": 0.8, "reasoning": "Nice"} That\'s my verdict.');

        $assertion = new JudgedBy($judge, 'Is this good?');
        $result = $assertion->check('Test response.');

        $this->assertTrue($result->passed);
    }

    public function testFailsOnInvalidJsonResponse(): void
    {
        $judge = $this->createMockJudge('I think it looks good!');

        $assertion = new JudgedBy($judge, 'Is this helpful?');
        $result = $assertion->check('Test response.');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Failed to parse', $result->message);
    }

    public function testDescription(): void
    {
        $judge = $this->createMock(ProviderInterface::class);

        $assertion = new JudgedBy($judge, 'Is this clear?', threshold: 0.8);

        $this->assertStringContainsString('Is this clear?', $assertion->getDescription());
        $this->assertStringContainsString('80%', $assertion->getDescription());
    }

    public function testWithOriginalPromptReturnsClone(): void
    {
        $judge = $this->createMock(ProviderInterface::class);

        $assertion = new JudgedBy($judge, 'Is this good?');
        $withPrompt = $assertion->withOriginalPrompt('What is 2+2?');

        $this->assertNotSame($assertion, $withPrompt);
    }

    public function testOriginalPromptIncludedInJudgeCall(): void
    {
        $judge = $this->createMock(ProviderInterface::class);
        $judge->expects($this->once())
            ->method('complete')
            ->with($this->callback(function (string $prompt): bool {
                return str_contains($prompt, 'What is 2+2?');
            }))
            ->willReturn(new Response(
                text: '{"pass": true, "score": 0.9, "reasoning": "Correct"}',
                model: 'test',
            ));

        $assertion = (new JudgedBy($judge, 'Is this correct?'))
            ->withOriginalPrompt('What is 2+2?');

        $assertion->check('4');
    }

    private function createMockJudge(string $response): ProviderInterface
    {
        $mock = $this->createMock(ProviderInterface::class);
        $mock->method('complete')
            ->willReturn(new Response(text: $response, model: 'judge-model'));

        return $mock;
    }
}
