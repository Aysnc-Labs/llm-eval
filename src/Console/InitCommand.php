<?php

/**
 * CLI command to initialize a new evaluation file.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Initialize a sample evaluation file.
 *
 * Usage:
 *   llm-eval init
 *   llm-eval init my-eval.php
 */
#[AsCommand(
    name: 'init',
    description: 'Create a sample evaluation file',
)]
class InitCommand extends Command
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument(
            'filename',
            InputArgument::OPTIONAL,
            'The evaluation file to create',
            'eval.php'
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = $input->getArgument('filename');

        if (!is_string($filename)) {
            $filename = 'eval.php';
        }

        if (file_exists($filename)) {
            $io->error("File already exists: {$filename}");

            return Command::FAILURE;
        }

        $template = $this->getTemplate();

        if (file_put_contents($filename, $template) === false) {
            $io->error("Could not write to: {$filename}");

            return Command::FAILURE;
        }

        $io->success("Created evaluation file: {$filename}");
        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Set your ANTHROPIC_API_KEY environment variable',
            "  2. Edit {$filename} to customize your evaluation",
            "  3. Run: <info>vendor/bin/llm-eval run {$filename}</info>",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Get the template for a new evaluation file.
     */
    private function getTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Aysnc\AI\LlmEval\Dataset\Dataset;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Providers\AnthropicProvider;

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Create provider (requires ANTHROPIC_API_KEY environment variable)
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    throw new RuntimeException('ANTHROPIC_API_KEY environment variable is required');
}

$provider = new AnthropicProvider($apiKey);

// Define test cases
$dataset = Dataset::fromArray([
    ['prompt' => 'What is 2+2?', 'expected' => '4'],
    ['prompt' => 'What is the capital of France?', 'expected' => 'Paris'],
]);

// Create and return the evaluation
return LlmEval::create('sample-eval')
    ->provider($provider)
    ->dataset($dataset)
    ->assertions(function ($expect, $testCase): void {
        $expected = $testCase->getExpected('default');
        if ($expected !== null) {
            $expect->contains($expected);
        }
    });

PHP;
    }
}
