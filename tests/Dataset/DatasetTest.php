<?php

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Dataset;

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\Dataset\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class DatasetTest extends PHPUnitTestCase
{
    public function testFromArray(): void
    {
        $dataset = Dataset::fromArray([
            ['prompt' => 'What is 2+2?', 'expected' => '4'],
            ['prompt' => 'What is 3+3?', 'expected' => '6'],
        ]);

        $this->assertSame(2, $dataset->count());

        $cases = $dataset->toArray();
        $this->assertSame('What is 2+2?', $cases[0]->prompt);
        $this->assertSame('4', $cases[0]->getExpected());
    }

    public function testFromCsvThrowsIfFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        Dataset::fromCsv('/nonexistent/file.csv');
    }

    public function testFromJsonThrowsIfFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        Dataset::fromJson('/nonexistent/file.json');
    }

    public function testFromCsvLoadsFile(): void
    {
        $csvPath = $this->createTempCsv([
            ['prompt', 'expected'],
            ['What is 2+2?', '4'],
            ['What is 3+3?', '6'],
        ]);

        try {
            $dataset = Dataset::fromCsv($csvPath);

            $this->assertSame(2, $dataset->count());

            $cases = $dataset->toArray();
            $this->assertSame('What is 2+2?', $cases[0]->prompt);
            $this->assertSame('4', $cases[0]->getExpected());
        } finally {
            unlink($csvPath);
        }
    }

    public function testFromJsonLoadsFile(): void
    {
        $jsonPath = $this->createTempJson([
            ['prompt' => 'What is 2+2?', 'expected' => '4'],
            ['prompt' => 'What is 3+3?', 'expected' => '6'],
        ]);

        try {
            $dataset = Dataset::fromJson($jsonPath);

            $this->assertSame(2, $dataset->count());

            $cases = $dataset->toArray();
            $this->assertSame('What is 2+2?', $cases[0]->prompt);
        } finally {
            unlink($jsonPath);
        }
    }

    public function testIteratorYieldsTestCases(): void
    {
        $dataset = Dataset::fromArray([
            ['prompt' => 'Test 1'],
            ['prompt' => 'Test 2'],
        ]);

        $prompts = [];
        foreach ($dataset as $testCase) {
            $this->assertInstanceOf(TestCase::class, $testCase);
            $prompts[] = $testCase->prompt;
        }

        $this->assertSame(['Test 1', 'Test 2'], $prompts);
    }

    /**
     * Create a temporary CSV file.
     *
     * @param array<array<string>> $rows
     */
    private function createTempCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test_csv_');
        assert(is_string($path));

        $handle = fopen($path, 'w');
        assert($handle !== false);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    /**
     * Create a temporary JSON file.
     *
     * @param array<array<string, mixed>> $data
     */
    private function createTempJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test_json_');
        assert(is_string($path));

        $json = json_encode($data);
        assert(is_string($json));

        file_put_contents($path, $json);

        return $path;
    }
}
