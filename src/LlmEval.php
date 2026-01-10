<?php

/**
 * Main entry point for LLM evaluations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval;

use Aysnc\AI\LlmEval\Assertions\AssertionInterface;
use Aysnc\AI\LlmEval\Assertions\JudgedBy;
use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\Dataset\TestCase;
use Aysnc\AI\LlmEval\Providers\AsyncProviderInterface;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use Closure;
use GuzzleHttp\Promise\Utils;
use InvalidArgumentException;

/**
 * LlmEval is the main entry point for creating and running LLM evaluations.
 *
 * Usage:
 *   $result = LlmEval::create('math-test')
 *       ->provider($anthropic)
 *       ->prompt('What is 2+2?')
 *       ->run();
 */
class LlmEval
{
    /**
     * The name of this evaluation.
     */
    protected string $name;

    /**
     * The LLM provider to use.
     */
    protected ?ProviderInterface $provider = null;

    /**
     * The prompt to send to the LLM.
     */
    protected ?string $prompt = null;

    /**
     * Provider options (model, max_tokens, etc.).
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Dataset for batch evaluations.
     */
    protected ?Dataset $dataset = null;

    /**
     * Assertion builder callback for dataset evaluations.
     *
     * @var Closure(Expectation, TestCase): void|null
     */
    protected ?Closure $assertionBuilder = null;

    /**
     * Create a new evaluation.
     *
     * @param string $name A descriptive name for this evaluation.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Static factory method for fluent API.
     *
     * @param string $name A descriptive name for this evaluation.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the LLM provider to use.
     */
    public function provider(ProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the prompt to send to the LLM.
     */
    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Set the model to use.
     */
    public function model(string $model): self
    {
        $this->options['model'] = $model;

        return $this;
    }

    /**
     * Set the maximum tokens for the response.
     */
    public function maxTokens(int $maxTokens): self
    {
        $this->options['max_tokens'] = $maxTokens;

        return $this;
    }

    /**
     * Set a custom option.
     */
    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Set a dataset for batch evaluations.
     */
    public function dataset(Dataset $dataset): self
    {
        $this->dataset = $dataset;

        return $this;
    }

    /**
     * Set an assertion builder for dataset evaluations.
     *
     * The callback receives an Expectation and TestCase, allowing you to
     * build assertions dynamically based on the test case data.
     *
     * @param Closure(Expectation, TestCase): void $builder
     */
    public function assertions(Closure $builder): self
    {
        $this->assertionBuilder = $builder;

        return $this;
    }

    /**
     * Start building expectations (assertions) for the response.
     */
    public function expect(): Expectation
    {
        return new Expectation($this);
    }

    /**
     * Run the evaluation and return the response (without assertions).
     *
     * @throws InvalidArgumentException If provider or prompt is not set.
     */
    public function run(): Response
    {
        return $this->executePrompt();
    }

    /**
     * Run the evaluation with assertions and return a Result.
     *
     * @internal Called by Expectation::run()
     *
     * @param array<AssertionInterface> $assertions The assertions to run.
     *
     * @throws InvalidArgumentException If provider or prompt is not set.
     */
    public function runWithAssertions(array $assertions): Result
    {
        $response = $this->executePrompt();

        $assertionResults = [];
        foreach ($assertions as $assertion) {
            // Inject original prompt into JudgedBy assertions for context
            if ($assertion instanceof JudgedBy && $this->prompt !== null) {
                $assertion = $assertion->withOriginalPrompt($this->prompt);
            }

            $assertionResults[] = $assertion->check($response->text);
        }

        return Result::fromAssertions($this->name, $response, $assertionResults);
    }

    /**
     * Run evaluations against all test cases in the dataset.
     *
     * @throws InvalidArgumentException If dataset or provider is not set.
     */
    public function runAll(): SuiteResult
    {
        if ($this->dataset === null) {
            throw new InvalidArgumentException('Dataset must be set before running runAll()');
        }

        if ($this->provider === null) {
            throw new InvalidArgumentException('Provider must be set before running evaluation');
        }

        if ($this->assertionBuilder === null) {
            throw new InvalidArgumentException('Assertion builder must be set via assertions() before running runAll()');
        }

        $assertionBuilder = $this->assertionBuilder;
        $results = [];

        foreach ($this->dataset as $testCase) {
            // Set the prompt from the test case
            $this->prompt = $testCase->prompt;

            // Build assertions for this test case
            $expectation = new Expectation($this);
            $assertionBuilder($expectation, $testCase);

            // Run and collect result
            $results[] = $this->runWithAssertions($expectation->getAssertions());
        }

        return SuiteResult::fromResults($this->name, $results);
    }

    /**
     * Run evaluations against all test cases in the dataset concurrently.
     *
     * This method requires the provider to implement AsyncProviderInterface.
     * All LLM requests are fired concurrently using Guzzle promises, which
     * can significantly speed up batch evaluations.
     *
     * @param int $concurrency Maximum number of concurrent requests (0 = unlimited).
     *
     * @throws InvalidArgumentException If dataset, provider, or assertion builder is not set.
     * @throws InvalidArgumentException If provider does not support async operations.
     */
    public function runAllParallel(int $concurrency = 0): SuiteResult
    {
        if ($this->dataset === null) {
            throw new InvalidArgumentException('Dataset must be set before running runAllParallel()');
        }

        if ($this->provider === null) {
            throw new InvalidArgumentException('Provider must be set before running evaluation');
        }

        if (!$this->provider instanceof AsyncProviderInterface) {
            throw new InvalidArgumentException(
                'Provider must implement AsyncProviderInterface for parallel execution. '
                . 'Use runAll() for sequential execution instead.'
            );
        }

        if ($this->assertionBuilder === null) {
            throw new InvalidArgumentException('Assertion builder must be set via assertions() before running runAllParallel()');
        }

        // Convert dataset generator to array (needed for parallel execution)
        $testCases = iterator_to_array($this->dataset);

        // Build promises for all test cases
        $promises = [];
        foreach ($testCases as $index => $testCase) {
            $promises[$index] = $this->provider->completeAsync($testCase->prompt, $this->options);
        }

        // If concurrency is limited, use pool pattern; otherwise resolve all at once
        if ($concurrency > 0) {
            $responses = $this->resolveWithConcurrency($promises, $concurrency);
        } else {
            // Wait for all promises to resolve
            /** @var array<int, Response> $responses */
            $responses = Utils::unwrap($promises);
        }

        // Build results for each test case
        $results = [];
        $assertionBuilder = $this->assertionBuilder;

        foreach ($testCases as $index => $testCase) {
            $response = $responses[$index];

            // Build assertions for this test case
            $expectation = new Expectation($this);
            $assertionBuilder($expectation, $testCase);

            // Apply assertions
            $assertionResults = [];
            foreach ($expectation->getAssertions() as $assertion) {
                if ($assertion instanceof JudgedBy) {
                    $assertion = $assertion->withOriginalPrompt($testCase->prompt);
                }
                $assertionResults[] = $assertion->check($response->text);
            }

            // Use metadata['name'] if set, otherwise use case index
            $caseName = is_string($testCase->metadata['name'] ?? null)
                ? $testCase->metadata['name']
                : "Case {$index}";

            $results[] = Result::fromAssertions(
                $this->name . ' - ' . $caseName,
                $response,
                $assertionResults
            );
        }

        return SuiteResult::fromResults($this->name, $results);
    }

    /**
     * Resolve promises with a concurrency limit.
     *
     * Uses a sliding window approach to limit concurrent requests.
     *
     * @param array<int, \GuzzleHttp\Promise\PromiseInterface> $promises
     * @param int $concurrency Maximum concurrent requests.
     *
     * @return array<int, Response>
     */
    private function resolveWithConcurrency(array $promises, int $concurrency): array
    {
        $responses = [];
        $chunks = array_chunk($promises, max(1, $concurrency), true);

        foreach ($chunks as $chunk) {
            /** @var array<int, Response> $chunkResponses */
            $chunkResponses = Utils::unwrap($chunk);
            $responses += $chunkResponses;
        }

        return $responses;
    }

    /**
     * Execute the prompt and return the response.
     *
     * @throws InvalidArgumentException If provider or prompt is not set.
     */
    private function executePrompt(): Response
    {
        if ($this->provider === null) {
            throw new InvalidArgumentException('Provider must be set before running evaluation');
        }

        if ($this->prompt === null) {
            throw new InvalidArgumentException('Prompt must be set before running evaluation');
        }

        return $this->provider->complete($this->prompt, $this->options);
    }

    /**
     * Get the evaluation name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the configured prompt.
     */
    public function getPrompt(): ?string
    {
        return $this->prompt;
    }

    /**
     * Get the configured options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Check if a provider has been configured.
     */
    public function hasProvider(): bool
    {
        return $this->provider !== null;
    }
}
