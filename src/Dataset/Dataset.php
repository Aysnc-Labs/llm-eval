<?php

/**
 * Dataset for batch evaluations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Dataset;

use ArrayIterator;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use RuntimeException;
use Traversable;

/**
 * A collection of test cases that can be loaded from various sources.
 *
 * Implements IteratorAggregate for memory-efficient iteration over large datasets.
 *
 * @implements IteratorAggregate<int, TestCase>
 */
class Dataset implements IteratorAggregate
{
    /**
     * @param array<TestCase> $testCases The test cases (for array-based datasets).
     * @param string|null $filePath Path to file (for lazy loading).
     * @param string|null $fileType Type of file ('csv' or 'json').
     */
    private function __construct(
        private readonly array $testCases = [],
        private readonly ?string $filePath = null,
        private readonly ?string $fileType = null,
    ) {
    }

    /**
     * Create a dataset from an array of test cases.
     *
     * @param array<array<string, mixed>> $items Array of associative arrays.
     */
    public static function fromArray(array $items): self
    {
        $testCases = array_map(
            static fn (array $item): TestCase => TestCase::fromArray($item),
            $items,
        );

        return new self($testCases);
    }

    /**
     * Create a dataset from a CSV file.
     *
     * The CSV must have a header row. The 'prompt' column is required.
     *
     * @param string $path Path to the CSV file.
     */
    public static function fromCsv(string $path): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('CSV file not found: %s', $path));
        }

        return new self([], $path, 'csv');
    }

    /**
     * Create a dataset from a JSON file.
     *
     * The JSON must be an array of objects, each with a 'prompt' key.
     *
     * @param string $path Path to the JSON file.
     */
    public static function fromJson(string $path): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('JSON file not found: %s', $path));
        }

        return new self([], $path, 'json');
    }

    /**
     * @return Traversable<int, TestCase>
     */
    public function getIterator(): Traversable
    {
        if ($this->filePath !== null) {
            return $this->iterateFile();
        }

        return new ArrayIterator($this->testCases);
    }

    /**
     * Get all test cases as an array.
     *
     * Note: For large datasets, prefer iteration to avoid memory issues.
     *
     * @return array<TestCase>
     */
    public function toArray(): array
    {
        if ($this->filePath !== null) {
            return iterator_to_array($this->iterateFile());
        }

        return $this->testCases;
    }

    /**
     * Count the number of test cases.
     */
    public function count(): int
    {
        if ($this->filePath !== null) {
            return iterator_count($this->iterateFile());
        }

        return count($this->testCases);
    }

    /**
     * Iterate over a file lazily.
     *
     * @return Generator<int, TestCase>
     */
    private function iterateFile(): Generator
    {
        if ($this->fileType === 'csv') {
            yield from $this->iterateCsv();
        } elseif ($this->fileType === 'json') {
            yield from $this->iterateJson();
        }
    }

    /**
     * Iterate over a CSV file.
     *
     * @return Generator<int, TestCase>
     */
    private function iterateCsv(): Generator
    {
        $handle = fopen($this->filePath ?? '', 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Failed to open CSV file: %s', $this->filePath));
        }

        try {
            $headerRow = fgetcsv($handle);
            if ($headerRow === false) {
                return;
            }

            // Filter and convert headers to strings
            $headers = array_map(
                static fn (mixed $h): string => is_string($h) ? $h : '',
                $headerRow,
            );

            $index = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    continue; // Skip malformed rows
                }

                // Convert row values to strings
                $values = array_map(
                    static fn (mixed $v): string => is_string($v) ? $v : '',
                    $row,
                );

                /** @var array<string, string> $data */
                $data = array_combine($headers, $values);

                yield $index++ => TestCase::fromArray($data);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Iterate over a JSON file.
     *
     * @return Generator<int, TestCase>
     */
    private function iterateJson(): Generator
    {
        $contents = file_get_contents($this->filePath ?? '');
        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read JSON file: %s', $this->filePath));
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Invalid JSON in file: %s', $this->filePath));
        }

        $index = 0;
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            yield $index++ => TestCase::fromArray($item);
        }
    }
}
