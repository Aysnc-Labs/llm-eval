<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests;

use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Result;
use Aysnc\AI\LlmEval\SuiteResult;
use PHPUnit\Framework\TestCase;

class SuiteResultTest extends TestCase
{
    public function testFromResultsAllPassed(): void
    {
        $results = [
            $this->createResult(true),
            $this->createResult(true),
            $this->createResult(true),
        ];

        $suite = SuiteResult::fromResults('test-suite', $results);

        $this->assertTrue($suite->passed);
        $this->assertSame(1.0, $suite->passRate);
        $this->assertSame(3, $suite->passedCount());
        $this->assertSame(0, $suite->failedCount());
        $this->assertSame('100.0%', $suite->passRatePercent());
    }

    public function testFromResultsSomeFailed(): void
    {
        $results = [
            $this->createResult(true),
            $this->createResult(false),
            $this->createResult(true),
            $this->createResult(false),
        ];

        $suite = SuiteResult::fromResults('test-suite', $results);

        $this->assertFalse($suite->passed);
        $this->assertSame(0.5, $suite->passRate);
        $this->assertSame(2, $suite->passedCount());
        $this->assertSame(2, $suite->failedCount());
        $this->assertSame('50.0%', $suite->passRatePercent());
    }

    public function testGetFailures(): void
    {
        $passed = $this->createResult(true);
        $failed1 = $this->createResult(false);
        $failed2 = $this->createResult(false);

        $suite = SuiteResult::fromResults('test', [$passed, $failed1, $failed2]);

        $failures = $suite->getFailures();
        $this->assertCount(2, $failures);
    }

    public function testEmptyResults(): void
    {
        $suite = SuiteResult::fromResults('empty', []);

        $this->assertTrue($suite->passed); // No failures = passed
        $this->assertSame(0.0, $suite->passRate);
        $this->assertSame(0, $suite->totalCount());
    }

    private function createResult(bool $passed): Result
    {
        return new Result(
            name: 'test',
            response: new Response(text: 'test', model: 'test'),
            assertionResults: [],
            passed: $passed,
        );
    }
}
