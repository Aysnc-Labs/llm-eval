# LLM-Eval

![GitHub Actions](https://github.com/Aysnc-Labs/llm-eval/actions/workflows/test.yml/badge.svg)
![Maintenance](https://img.shields.io/badge/Actively%20Maintained-yes-green.svg)

A PHP package for evaluating LLM outputs. Test your prompts, validate responses, and ensure your AI features work correctly.

## Installation

```bash
composer require aysnc/llm-eval
```

## Quick Start

```php
<?php

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

$provider = new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));

$dataset = Dataset::fromArray([
    ['prompt' => 'What is 2+2? Reply with just the number.', 'expected' => '4'],
    ['prompt' => 'What is the capital of France? Reply with just the city name.', 'expected' => 'Paris'],
    ['prompt' => 'Is the sky blue? Reply with just yes or no.', 'expected' => 'yes'],
]);

$results = LlmEval::create('quick-start')
    ->provider($provider)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected(), caseSensitive: false);
    })
    ->runAll();

echo "Pass rate: {$results->passRatePercent()}\n";
// Pass rate: 100.0%
```

## Datasets

Load test cases from arrays, CSV files, or JSON files.

```php
// Inline array
$dataset = Dataset::fromArray([
    ['prompt' => 'What is 2+2?', 'expected' => '4'],
]);

// CSV file (columns: prompt, expected)
$dataset = Dataset::fromCsv(__DIR__ . '/data/capitals.csv');

// JSON file (array of objects with prompt + expected keys)
$dataset = Dataset::fromJson(__DIR__ . '/data/questions.json');
```

### Multiple Expected Values

The `expected` key can be a string (single value) or an array (multiple values):

```php
// Single expected value — accessed via $testCase->getExpected()
['prompt' => 'What is 2+2?', 'expected' => '4']

// Multiple expected values — accessed via $testCase->getExpected('name'), etc.
['prompt' => 'Return JSON with name and age.', 'expected' => ['name' => 'Alice', 'age' => '30']]
```

CSV files use column prefixes instead: `expected_name`, `expected_age` (flat format, parsed into the same array internally).

Any keys that aren't `prompt` or `expected` become metadata, accessible via `$testCase->getData('key')`.

## Assertions

### Text

```php
$expect->contains('Paris');
$expect->contains('paris', caseSensitive: false);
$expect->notContains('London');
$expect->matchesRegex('/\d{4}-\d{2}-\d{2}/');
$expect->minLength(10);
$expect->maxLength(500);
```

### JSON

```php
$expect->isJson();
```

### Tool Calls

```php
$expect->calledTool('get_weather');
$expect->calledTool('get_weather', times: 2);
$expect->toolCallHasParam('get_weather', 'location');
$expect->toolCallHasParam('get_weather', 'location', 'Paris');
$expect->calledToolCount(3);
$expect->didNotCallTool('dangerous_function');
```

### LLM-as-Judge

```php
$expect->judgedBy($judge, 'Is this response helpful and accurate?');
$expect->judgedBy($judge, 'Is this concise?', threshold: 0.9);
```

### Conversation (multi-turn)

```php
$expect->turnCount(2);
$expect->usedTool('calculate');
$expect->conversationContains('42');
```

### Custom

```php
$expect->assert(new MyCustomAssertion());
```

## Tool Call Testing

Test that your LLM calls tools with the right parameters — without executing a full conversation loop.

```php
$tools = [
    [
        'name' => 'get_weather',
        'description' => 'Get weather for a location',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string'],
            ],
            'required' => ['location'],
        ],
    ],
];

$results = LlmEval::create('tool-test')
    ->provider($provider)
    ->option('tools', $tools)
    ->dataset($dataset)
    ->assertions(function ($expect): void {
        $expect->calledTool('get_weather');
        $expect->toolCallHasParam('get_weather', 'location', 'Paris');
    })
    ->runAll();
```

## Structured Output

Validate that the LLM returns well-formed JSON with the right content. Combine `isJson()` with `contains()` or multiple expected values.

```php
$dataset = Dataset::fromArray([
    [
        'prompt' => 'Return a JSON object with keys "name" and "age". Use name "Alice" and age 30. Only output JSON.',
        'expected' => ['name' => 'Alice', 'age' => '30'],
    ],
    [
        'prompt' => 'Return a JSON array of three colors: red, green, blue. Only output JSON.',
        'expected' => 'red',
    ],
]);

$results = LlmEval::create('json-output')
    ->provider($provider)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->isJson()
            ->contains($testCase->getExpected())
            ->contains($testCase->getExpected('name'));
    })
    ->runAll();
```

## Multi-Turn Conversations

Test agentic workflows where the LLM calls tools, receives results, and continues reasoning.

```php
use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\CallableToolExecutor;
use Aysnc\AI\LlmEval\Providers\ToolCall;
use Aysnc\AI\LlmEval\Providers\ToolResult;

$tools = [
    [
        'name' => 'calculate',
        'description' => 'Evaluate a math expression',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'expression' => ['type' => 'string'],
            ],
            'required' => ['expression'],
        ],
    ],
];

$executor = new CallableToolExecutor([
    'calculate' => function (ToolCall $tc): ToolResult {
        $expr = $tc->getParam('expression');
        $result = match ($expr) {
            '6 * 7', '6*7' => '42',
            default => 'unknown',
        };

        return new ToolResult($tc->id, $result);
    },
]);

$dataset = Dataset::fromArray([
    ['prompt' => 'Use the calculate tool to compute 6 * 7.', 'expected' => '42'],
]);

$results = LlmEval::createConversation('math-agent')
    ->provider($provider)
    ->withTools($tools)
    ->executor($executor)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected())
            ->usedTool('calculate')
            ->turnCount(2);
    })
    ->runAll();
```

### Multi-Turn Datasets

Use a `turns` array to define multi-turn conversations. Each turn has its own `prompt` and optional `expected` values for per-turn assertions. Use `getTurn()` to access the 1-indexed turn number.

In multi-turn conversations, the judge automatically receives the full conversation history — including all tool calls, tool results, and messages — so it can evaluate whether the response correctly builds on previous turns.

```php
$judge = new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));

$dataset = Dataset::fromArray([
    [
        'turns' => [
            ['prompt' => 'What is the weather in Paris?', 'expected' => '22'],
            ['prompt' => 'Now check Tokyo', 'expected' => '18'],
            ['prompt' => 'Which city was warmer?', 'expected' => 'Paris'],
        ],
    ],
]);

$results = LlmEval::createConversation('multi-turn')
    ->provider($provider)
    ->withTools($tools)
    ->executor($executor)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase) use ($judge): void {
        $expect->contains($testCase->getExpected());

        // Only assert tool usage on turns that call the tool.
        if ($testCase->getTurn() <= 2) {
            $expect->usedTool('get_weather');
        }

        // The judge sees full conversation history — it knows which cities were discussed.
        if ($testCase->getTurn() === 3) {
            $expect->judgedBy(
                judge: $judge,
                criteria: 'Does the response correctly identify the warmer city based on the earlier temperatures?',
            );
        }
    })
    ->runAll();
```

## LLM-as-Judge

Use one LLM to evaluate another's response quality. When used inside a `ConversationEval`, the judge automatically receives the full conversation history for context-aware evaluation.

```php
$judge = new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));

$results = LlmEval::create('quality-check')
    ->provider($provider)
    ->dataset($dataset)
    ->assertions(function ($expect) use ($judge): void {
        $expect->judgedBy(
            judge: $judge,
            criteria: 'Is this response helpful, accurate, and concise?',
            threshold: 0.8,
        );
    })
    ->runAll();
```

## CLI Runner

```bash
# Initialize config file
vendor/bin/llm-eval init

# Run all evaluations
vendor/bin/llm-eval run

# Run a specific evaluation
vendor/bin/llm-eval run my-test

# Run in parallel
vendor/bin/llm-eval run --parallel --concurrency=10

# Clear response cache
vendor/bin/llm-eval cache:clear
```

### Output

```
LLM-Eval Runner
===============

Running evaluations...

  PASS simple - Case 0
  PASS simple - Case 1
  FAIL simple - Case 2
       Got: "The sky appears blue due to Rayleigh scattering..."
       → Text does not contain "yes"
  PASS conversation-json - compare-two-cities - Turn 1
  PASS conversation-json - compare-two-cities - Turn 2
  PASS conversation-json - compare-two-cities - Turn 3
       → Score: 100% (threshold: 70%) - The response correctly identifies Paris as the warmer city.
  PASS llm-judge - photosynthesis
       → Score: 95% (threshold: 70%) - Clear, accurate explanation mentioning plants and sunlight.

Summary
-------
  Total       7
  Passed      6
  Failed      1
  Pass Rate   85.7%
  Duration    4.32s
```

## Providers

### Anthropic Claude

Direct API access. Get your key at [console.anthropic.com](https://console.anthropic.com).

```php
use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

$provider = new AnthropicProvider(
    apiKey: getenv('ANTHROPIC_API_KEY'),
);
```

### AWS Bedrock

Uses the Converse API — works with Claude, Titan, Llama, Mistral, and other Bedrock models. Requires `composer require aws/aws-sdk-php`. See [AWS Bedrock docs](https://docs.aws.amazon.com/bedrock/).

```php
use Aysnc\AI\LlmEval\Providers\BedrockProvider;

// Explicit credentials
$provider = new BedrockProvider(
    region: 'us-east-1',
    accessKeyId: 'AKIA...',
    secretAccessKey: 'secret...',
);

// Or default credential chain (env vars, ~/.aws/credentials, IAM role)
$provider = new BedrockProvider(region: 'us-east-1');
```

## Caching & Parallel Execution

Wrap any provider with `CachingProvider` for deterministic, cost-free reruns. Combine with `runAllParallel()` for speed.

```php
use Aysnc\AI\LlmEval\Cache\CachingProvider;
use Aysnc\AI\LlmEval\Cache\FilesystemCache;

$cache = new FilesystemCache(__DIR__ . '/.llm-cache');
$cached = new CachingProvider($provider, $cache);

$results = LlmEval::create('large-eval')
    ->provider($cached)
    ->dataset($dataset)
    ->assertions($assertions)
    ->runAllParallel(concurrency: 10);
```

## Configuration

The CLI reads `llm-eval.php` from your project root:

```php
<?php

use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

return [
    'provider' => new AnthropicProvider(getenv('ANTHROPIC_API_KEY')),
    'directory' => __DIR__ . '/evals',
    'cache' => true,
    'cacheTtl' => 0,
    'parallel' => false,
    'concurrency' => 5,
];
```

## Requirements

- PHP 8.3+
- `guzzlehttp/guzzle` ^7.10
- `aws/aws-sdk-php` ^3.0 (optional, for Bedrock)
