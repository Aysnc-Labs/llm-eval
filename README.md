# LLM-Eval

![GitHub Actions](https://github.com/Aysnc-Labs/llm-eval/actions/workflows/test.yml/badge.svg)
![Maintenance](https://img.shields.io/badge/Actively%20Maintained-yes-green.svg)

A PHP package for evaluating LLM outputs. Test your prompts, validate responses, and ensure your AI features work correctly.

## Installation

```bash
composer require aysnc/llm-eval
```

## Configuration

Create `llm-eval.php` in your project root:

```php
<?php

use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

return [
    'provider'    => new AnthropicProvider(getenv('ANTHROPIC_API_KEY')),
    'directory'   => __DIR__ . '/evals',
    'cache'       => true,
    'cacheTtl'    => 0,
    'parallel'    => false,
    'concurrency' => 5,
];
```

| Option | Type | Default | Description |
|---|---|---|---|
| `provider` | `ProviderInterface` | — | The LLM provider shared across all eval files |
| `directory` | `string` | `'evals'` | Directory containing your eval files |
| `cache` | `bool\|string` | `false` | `true` uses `.llm-cache/`, or pass a custom path |
| `cacheTtl` | `int` | `0` | Cache lifetime in seconds (`0` = forever) |
| `parallel` | `bool` | `false` | Run evals in parallel by default |
| `concurrency` | `int` | `0` | Max concurrent requests when parallel (`0` = unlimited) |

## Quick Start

**1. Create an eval file** in your evals directory. Each file **returns** an `LlmEval` instance:

```php
// evals/simple.php
<?php

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;

$dataset = Dataset::fromArray([
    ['prompt' => 'What is 2+2? Reply with just the number.', 'expected' => '4'],
    ['prompt' => 'What is the capital of France? Reply with just the city name.', 'expected' => 'Paris'],
    ['prompt' => 'Is the sky blue? Reply with just yes or no.', 'expected' => 'yes'],
]);

return LlmEval::create('quick-start')
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected(), caseSensitive: false);
    });
```

**2. Run it:**

```bash
vendor/bin/llm-eval run
```

```
LLM-Eval Runner
===============

  PASS quick-start - Case 0
  PASS quick-start - Case 1
  PASS quick-start - Case 2

Summary
-------
  Total       3
  Passed      3
  Failed      0
  Pass Rate   100.0%
  Duration    1.24s
```

The config provides the LLM provider, the eval file defines what to test — no `->provider()` or `->runAll()` needed in the file.

## Core Concepts

An evaluation has three parts: a **provider** (which LLM to call), a **dataset** (prompts + expected answers), and **assertions** (how to check the response).

### Datasets

A dataset is a collection of test cases. Each test case has a `prompt` and an optional `expected` value.

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

The `expected` key can be a single value or multiple named values:

```php
// Single — accessed via $testCase->getExpected()
['prompt' => 'What is 2+2?', 'expected' => '4']

// Multiple — accessed via $testCase->getExpected('name'), $testCase->getExpected('age')
['prompt' => 'Return JSON with name and age.', 'expected' => ['name' => 'Alice', 'age' => '30']]
```

CSV files use column prefixes for multiple values: `expected_name`, `expected_age`.

Any keys that aren't `prompt` or `expected` become metadata, accessible via `$testCase->getData('key')`.

### Assertions

Assertions define what "correct" means for a response. You chain them inside the `assertions()` callback.

**Text**

```php
$expect->contains('Paris');
$expect->contains('paris', caseSensitive: false);
$expect->notContains('London');
$expect->matchesRegex('/\d{4}-\d{2}-\d{2}/');
$expect->minLength(10);
$expect->maxLength(500);
```

**JSON**

```php
$expect->isJson();
```

**Custom**

```php
$expect->assert(new MyCustomAssertion());
```

There are also assertions for [tool calls](#tool-call-testing), [multi-turn conversations](#multi-turn-conversations), and [LLM-as-judge](#llm-as-judge) — covered in the sections below.

## Testing Scenarios

### Structured Output

Validate that the LLM returns well-formed JSON with the right content. Combine `isJson()` with `contains()` or multiple expected values.

```php
// evals/json-output.php
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

return LlmEval::create('json-output')
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->isJson()
            ->contains($testCase->getExpected())
            ->contains($testCase->getExpected('name'));
    });
```

### Tool Call Testing

Test that your LLM calls tools with the right parameters — without executing a full conversation loop. This uses `LlmEval::create()` (not `createConversation`) since you're only checking the first response.

```php
// evals/tool-test.php
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

return LlmEval::create('tool-test')
    ->option('tools', $tools)
    ->dataset($dataset)
    ->assertions(function ($expect): void {
        $expect->calledTool('get_weather');
        $expect->toolCallHasParam('get_weather', 'location', 'Paris');
    });
```

**Available tool call assertions:**

```php
$expect->calledTool('get_weather');
$expect->calledTool('get_weather', times: 2);
$expect->toolCallHasParam('get_weather', 'location');
$expect->toolCallHasParam('get_weather', 'location', 'Paris');
$expect->calledToolCount(3);
$expect->didNotCallTool('dangerous_function');
```

### Multi-Turn Conversations

Test agentic workflows where the LLM calls tools, receives results, and continues reasoning. Use `LlmEval::createConversation()` with a tool executor that returns simulated results.

```php
// evals/math-agent.php
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

return LlmEval::createConversation('math-agent')
    ->withTools($tools)
    ->executor($executor)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected())
            ->usedTool('calculate')
            ->turnCount(2);
    });
```

**Available conversation assertions:**

```php
$expect->turnCount(2);
$expect->usedTool('calculate');
$expect->conversationContains('42');
```

#### Multi-Turn Datasets

Use a `turns` array to test conversations with multiple user messages. Each turn has its own `prompt` and optional `expected` values for per-turn assertions. Use `getTurn()` to access the 1-indexed turn number.

```php
$dataset = Dataset::fromArray([
    [
        'turns' => [
            ['prompt' => 'What is the weather in Paris?', 'expected' => '22'],
            ['prompt' => 'Now check Tokyo', 'expected' => '18'],
            ['prompt' => 'Which city was warmer?', 'expected' => 'Paris'],
        ],
    ],
]);

return LlmEval::createConversation('multi-turn')
    ->withTools($tools)
    ->executor($executor)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected());

        if ($testCase->getTurn() <= 2) {
            $expect->usedTool('get_weather');
        }
    });
```

### LLM-as-Judge

Use one LLM to evaluate another's response quality. Instead of checking for exact strings, you describe what "good" looks like and a judge model scores the response 0-100%.

```php
// evals/quality-check.php
use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

$judge = new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));

return LlmEval::create('quality-check')
    ->dataset($dataset)
    ->assertions(function ($expect) use ($judge): void {
        $expect->judgedBy(
            judge: $judge,
            criteria: 'Is this response helpful, accurate, and concise?',
            threshold: 0.8,
        );
    });
```

#### Judging Conversations

For multi-turn conversations, you can use `judgedBy()` inside `assertions()` to judge per-turn, or use `->judge()` on the eval to run a single evaluation after all turns complete. The judge receives the full conversation history — all messages, tool calls, and results.

```php
return LlmEval::createConversation('multi-turn')
    ->withTools($tools)
    ->executor($executor)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected());
    })
    ->judge($judge, 'Did the model correctly identify the warmer city based on the earlier temperatures?');
```

## CLI Runner

```bash
# Run all eval files in the evals directory
vendor/bin/llm-eval run

# Run a specific eval file
vendor/bin/llm-eval run my-test

# Run in parallel
vendor/bin/llm-eval run --parallel --concurrency=10

# Verbose mode — shows judge reasoning and tool calls for passing tests
vendor/bin/llm-eval run -v

# JSON output
vendor/bin/llm-eval run --format=json

# Clear response cache
vendor/bin/llm-eval cache:clear

# Scaffold a new eval file
vendor/bin/llm-eval init
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

With `-v`, passing tests also show judge scores and tool call details.

## Providers

### Anthropic Claude

Direct API access. Get your key at [console.anthropic.com](https://console.anthropic.com).

```php
$provider = new AnthropicProvider(
    apiKey: getenv('ANTHROPIC_API_KEY'),
);
```

Default model: `claude-sonnet-4-20250514`

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

Default model: `anthropic.claude-3-5-sonnet-20241022-v2:0`

### Changing the Model

Use `->model()` to override the default model for any provider:

```php
return LlmEval::create('eval-name')
    ->model('claude-opus-4-20250514')
    ->dataset($dataset)
    ->assertions($assertions);
```

This works with both `AnthropicProvider` (Anthropic model IDs) and `BedrockProvider` (Bedrock model IDs).

You can also set `->maxTokens(2048)` to override the default max tokens (1024).

## Programmatic API

If you need to run evals from PHP code — inside a test suite, a CI script, or anywhere you want to work with the results directly — use `->provider()` and `->runAll()`:

```php
$provider = new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));

$results = LlmEval::create('quick-start')
    ->provider($provider)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expect->contains($testCase->getExpected());
    })
    ->runAll();

echo "Pass rate: {$results->passRatePercent()}\n";
// Pass rate: 100.0%
```

## Requirements

- PHP 8.3+
- `guzzlehttp/guzzle` ^7.10
- `aws/aws-sdk-php` ^3.0 (optional, for Bedrock)
