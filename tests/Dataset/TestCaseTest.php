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

        $this->assertSame('What is 2+2?', $testCase->getPrompt());
        $this->assertSame(['answer' => '4'], $testCase->getData('expected'));
        $this->assertSame('easy', $testCase->getData('difficulty'));
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

        $this->assertSame('What is 2+2?', $testCase->getPrompt());
    }

    public function testFromArrayWithExpectedColumn(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'What is 2+2?',
            'expected' => '4',
        ]);

        $this->assertSame('4', $testCase->getExpected());
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

    public function testFromArrayWithExpectedArray(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'Return JSON with name and age.',
            'expected' => ['name' => 'Alice', 'age' => '30'],
        ]);

        $this->assertSame('Alice', $testCase->getExpected('name'));
        $this->assertSame('30', $testCase->getExpected('age'));
        $this->assertNull($testCase->getExpected()); // No 'default' key.
    }

    public function testFromArrayWithMetadata(): void
    {
        $testCase = TestCase::fromArray([
            'prompt' => 'Test',
            'category' => 'math',
            'difficulty' => 'easy',
        ]);

        $this->assertSame('math', $testCase->getData('category'));
        $this->assertSame('easy', $testCase->getData('difficulty'));
    }
}
