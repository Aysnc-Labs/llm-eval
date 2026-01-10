<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\Contains;
use PHPUnit\Framework\TestCase;

class ContainsTest extends TestCase
{
    public function testPassesWhenTextContainsNeedle(): void
    {
        $assertion = new Contains('hello');
        $result = $assertion->check('hello world');

        $this->assertTrue($result->passed);
    }

    public function testFailsWhenTextDoesNotContainNeedle(): void
    {
        $assertion = new Contains('foo');
        $result = $assertion->check('hello world');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('does not contain', $result->message);
    }

    public function testCaseSensitiveByDefault(): void
    {
        $assertion = new Contains('Hello');
        $result = $assertion->check('hello world');

        $this->assertFalse($result->passed);
    }

    public function testCaseInsensitiveOption(): void
    {
        $assertion = new Contains('Hello', caseSensitive: false);
        $result = $assertion->check('hello world');

        $this->assertTrue($result->passed);
    }

    public function testDescription(): void
    {
        $assertion = new Contains('test');
        $this->assertSame('Contains "test"', $assertion->getDescription());

        $assertion = new Contains('test', caseSensitive: false);
        $this->assertStringContainsString('case-insensitive', $assertion->getDescription());
    }
}
