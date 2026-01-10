<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Assertions;

use Aysnc\AI\LlmEval\Assertions\IsJson;
use PHPUnit\Framework\TestCase;

class IsJsonTest extends TestCase
{
    public function testPassesForValidJson(): void
    {
        $assertion = new IsJson();

        $this->assertTrue($assertion->check('{"key": "value"}')->passed);
        $this->assertTrue($assertion->check('[]')->passed);
        $this->assertTrue($assertion->check('"string"')->passed);
        $this->assertTrue($assertion->check('123')->passed);
        $this->assertTrue($assertion->check('true')->passed);
        $this->assertTrue($assertion->check('null')->passed);
    }

    public function testFailsForInvalidJson(): void
    {
        $assertion = new IsJson();

        $this->assertFalse($assertion->check('not json')->passed);
        $this->assertFalse($assertion->check('{invalid}')->passed);
        $this->assertFalse($assertion->check('')->passed);
    }

    public function testDescription(): void
    {
        $assertion = new IsJson();
        $this->assertSame('Is valid JSON', $assertion->getDescription());
    }
}
