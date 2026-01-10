<?php

/**
 * Main entry point for LLM evaluations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval;

use Aysnc\AI\LlmEval\Assertions\AssertionInterface;
use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\Dataset\TestCase;
use Aysnc\AI\LlmEval\Providers\ProviderInterface;
use Aysnc\AI\LlmEval\Providers\Response;
use Closure;
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
}
