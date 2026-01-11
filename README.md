# LLM-Eval

> **⚠️ Work In Progress** - This package is under active development. APIs may change.

A PHP package for systematically evaluating LLM outputs. Test your prompts, validate responses, and ensure your AI features work as expected.

## Why LLM-Eval?

Testing LLM applications is hard. Outputs are non-deterministic, prompts change frequently, and traditional unit tests don't capture the nuance of natural language. LLM-Eval provides:

- **Fluent API** for defining evaluations
- **Powerful assertions** (text matching, JSON validation, regex, LLM-as-judge)
- **Tool call testing** (verify your AI agents call the right functions)
- **Dataset support** (CSV, JSON, arrays)
- **Parallel execution** (run hundreds of tests quickly)
- **Multiple providers** (Anthropic Claude, AWS Bedrock)

## Quick Example

```php
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\AnthropicProvider;
use Aysnc\AI\LlmEval\Dataset\Dataset;

$provider = new AnthropicProvider('your-api-key');

$dataset = Dataset::fromArray([
    ['prompt' => 'What is 2+2?', 'expected' => '4'],
    ['prompt' => 'What is the capital of France?', 'expected' => 'Paris'],
]);

$results = LlmEval::create('math-test')
    ->provider($provider)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase) {
        $expect->contains($testCase->getExpected('default'), caseSensitive: false);
    })
    ->run();

echo "Pass rate: {$results->passRate()}%\n";
```

## Features

### ✅ Completed (Phase 1-5)

- **Providers**: Anthropic Claude (direct API), AWS Bedrock (Converse API)
- **Assertions**: Contains, regex, JSON validation, length checks, tool call validation
- **LLM-as-Judge**: Use an LLM to evaluate another LLM's output
- **Datasets**: Load from CSV, JSON, or arrays
- **Parallel Execution**: Run evaluations concurrently with configurable limits
- **CLI Runner**: `llm-eval run [eval-name]` with caching support
- **Tool Call Testing**: Verify LLMs call functions correctly with proper parameters

### 🚧 Planned (Phase 6)

- **Multi-turn conversations**: Test agentic workflows with back-and-forth tool execution
- **Conversation helper**: Manage conversation state and tool execution loops
- **Tool executor interface**: Standardized mocking for tool calls

## Installation

```bash
composer require aysnc/llm-eval
```

### Optional Dependencies

```bash
# For AWS Bedrock support
composer require aws/aws-sdk-php
```

## Requirements

- PHP 8.3+
- Guzzle HTTP client

## Providers

### Anthropic Claude (Direct API)

```php
use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

$provider = new AnthropicProvider('your-api-key');
```

### AWS Bedrock (Any Model)

Works with Claude, Titan, Llama, Mistral, and other Bedrock models via the Converse API:

```php
use Aysnc\AI\LlmEval\Providers\BedrockProvider;

$provider = new BedrockProvider(
    region: 'us-east-1',
    accessKeyId: 'AKIA...',
    secretAccessKey: 'secret...'
);

// Or use default credential chain (env vars, ~/.aws/credentials, IAM role)
$provider = new BedrockProvider(region: 'us-east-1');
```

## Assertions

```php
->assertions(function ($expect, $testCase) {
    // Text matching
    $expect->contains('Paris');
    $expect->notContains('London');
    $expect->matchesRegex('/\d{4}-\d{2}-\d{2}/');

    // JSON validation
    $expect->isJson();
    $expect->matchesJsonSchema(['type' => 'object', 'properties' => [...]]);

    // Length checks
    $expect->minLength(10);
    $expect->maxLength(100);

    // Tool calls
    $expect->calledTool('get_weather');
    $expect->toolCallHasParam('get_weather', 'location', 'Paris');
    $expect->didNotCallTool('dangerous_function');

    // LLM-as-judge
    $expect->judgedBy($judgeProvider, 'Is this response helpful and accurate?');
});
```

## CLI Usage

```bash
# Initialize config
vendor/bin/llm-eval init

# Run all evaluations
vendor/bin/llm-eval run

# Run specific evaluation
vendor/bin/llm-eval run my-test

# Run with parallelization
vendor/bin/llm-eval run --parallel --concurrency=10

# Clear cache
vendor/bin/llm-eval cache:clear
```

## Configuration

Create `llm-eval.php` in your project root:

```php
<?php

use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

return [
    'provider' => new AnthropicProvider(getenv('ANTHROPIC_API_KEY')),
    'directory' => __DIR__ . '/evals',
    'cache' => true,
    'parallel' => false,
    'concurrency' => 5,
];
```

## Project Structure

```
your-project/
├── llm-eval.php          # Configuration
├── evals/                # Evaluation files
│   ├── math-test.php
│   ├── summarization.php
│   └── tool-calling.php
└── .llm-cache/          # Response cache (auto-created)
```

## Testing Tool Calls

```php
$tools = [
    [
        'name' => 'get_weather',
        'description' => 'Get weather for a location',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string'],
                'unit' => ['type' => 'string', 'enum' => ['celsius', 'fahrenheit']],
            ],
            'required' => ['location'],
        ],
    ],
];

return LlmEval::create('tool-test')
    ->provider($provider)
    ->option('tools', $tools)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase) {
        $expect->calledTool('get_weather');
        $expect->toolCallHasParam('get_weather', 'location', 'Paris');
    });
```

## LLM-as-Judge

Use one LLM to evaluate another's responses:

```php
$llm = new AnthropicProvider('api-key');
$judge = new AnthropicProvider('judge-api-key');

return LlmEval::create('quality-check')
    ->provider($llm)
    ->dataset($dataset)
    ->assertions(function ($expect) use ($judge) {
        $expect->judgedBy(
            judge: $judge,
            criteria: 'Is this response helpful, accurate, and concise?',
            passingScore: 0.8
        );
    });
```

## Parallel Execution

```php
use Aysnc\AI\LlmEval\Providers\CachingProvider;

// Wrap provider with caching
$cachedProvider = new CachingProvider($provider, $cache);

$results = LlmEval::create('large-test')
    ->provider($cachedProvider)
    ->dataset($largeDataset)
    ->assertions($assertions)
    ->runAllParallel(concurrency: 10);
```

## Current Limitations

- **Single-turn only**: Cannot test multi-turn conversations with tool execution loops (planned for Phase 6)
- **No streaming support**: Responses are loaded fully before evaluation
- **Limited providers**: Currently Anthropic and Bedrock only (OpenAI, Ollama planned)
