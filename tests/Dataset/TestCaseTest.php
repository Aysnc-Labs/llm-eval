<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Dataset;

use Aysnc\AI\LlmEval\Dataset\TestCase;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCaseTest extends PHPUnitTestCase
{
    public function testConstructor(): void
    {
        $testCase = new TestCase(
            prompt: 'What is 2+2?',
            expected: ['answer' => '4'],
            metadata: ['difficulty' => 'easy'],
        );

        $this->assertSame('What is 2+2?', $testCase->prompt);
        $this->assertSame(['answer' => '4'], $testCase->expected);
        $this->assertSame(['difficulty' => 'easy'], $testCase->metadata);
    }

    public function testGetExpected(): void
    {
        $testCase = new TestCase(
            prompt: 'Test',
            expected: ['answer' => '4', 'format' => 'number'],
        );

        $this->assertSame('4', $testCase->getExpected('answer'));
        $this->assertSame('number', $testCase->getExpected('format'));
        $this->assertNull($testCase->getExpected('nonexistent'));
    }

    public function testHasExpected(): void
    {
        $testCase = new TestCase(
            prompt: 'Test',
            expected: ['answer' => '4'],
        );

        $this->assertTrue($testCase->hasExpected('answer'));
        $this->assertFalse($testCase->hasExpected('nonexistent'));
    }

    public function testFromArrayWithPrompt(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'What is 2+2?',
        ]);

        $this->assertSame('What is 2+2?', $testCase->prompt);
    }

    public function testFromArrayWithExpectedColumn(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'What is 2+2?',
            'expected' => '4',
        ]);

        $this->assertSame('4', $testCase->getExpected('default'));
    }

    public function testFromArrayWithExpectedPrefixedColumns(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'Test',
            'expected_answer' => '4',
            'expected_format' => 'number',
        ]);

        $this->assertSame('4', $testCase->getExpected('answer'));
        $this->assertSame('number', $testCase->getExpected('format'));
    }

    public function testFromArrayWithMetadata(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'Test',
            'category' => 'math',
            'difficulty' => 'easy',
        ]);

        $this->assertSame('math', $testCase->metadata['category']);
        $this->assertSame('easy', $testCase->metadata['difficulty']);
    }
}
