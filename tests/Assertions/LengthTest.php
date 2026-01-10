<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\MaxLength;
use Aysnc\AI\LlmEval\Assertions\MinLength;
use PHPUnit\Framework\TestCase;

class LengthTest extends TestCase
{
    public function testMaxLengthPassesWhenUnder(): void
    {
        $assertion = new MaxLength(10);
        $result = $assertion->check('hello');

        $this->assertTrue($result->passed);
    }

    public function testMaxLengthPassesWhenExact(): void
    {
        $assertion = new MaxLength(5);
        $result = $assertion->check('hello');

        $this->assertTrue($result->passed);
    }

    public function testMaxLengthFailsWhenOver(): void
    {
        $assertion = new MaxLength(3);
        $result = $assertion->check('hello');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('exceeds maximum', $result->message);
    }

    public function testMinLengthPassesWhenOver(): void
    {
        $assertion = new MinLength(3);
        $result = $assertion->check('hello');

        $this->assertTrue($result->passed);
    }

    public function testMinLengthPassesWhenExact(): void
    {
        $assertion = new MinLength(5);
        $result = $assertion->check('hello');

        $this->assertTrue($result->passed);
    }

    public function testMinLengthFailsWhenUnder(): void
    {
        $assertion = new MinLength(10);
        $result = $assertion->check('hello');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('below minimum', $result->message);
    }

    public function testUsesMultibyteStringLength(): void
    {
        $assertion = new MaxLength(3);
        // 3 emoji characters (each is multiple bytes but 1 character)
        $result = $assertion->check('🎉🎊🎁');

        $this->assertTrue($result->passed);
    }
}
