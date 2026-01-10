<?php

/**
 * CLI command to run evaluations.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Console;

use Aysnc\AI\LlmEval\Assertions\AssertionResult;
use Aysnc\AI\LlmEval\LlmEval;
use Aysnc\AI\LlmEval\Result;
use Aysnc\AI\LlmEval\SuiteResult;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Run evaluations from the command line.
 *
 * Usage:
 *   llm-eval run eval.php
 *   llm-eval run eval.php --parallel
 *   llm-eval run eval.php --format=json
 */
#[AsCommand(
    name: 'run',
    description: 'Run an evaluation file',
)]
class RunCommand extends Command
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The evaluation file to run')
            ->addOption('parallel', 'p', InputOption::VALUE_NONE, 'Run evaluations in parallel')
            ->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Max concurrent requests (with --parallel)', '0')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (text, json)', 'text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        if (!is_string($file)) {
            $io->error('File argument must be a string');

            return Command::FAILURE;
        }

        if (!file_exists($file)) {
            $io->error("Evaluation file not found: {$file}");

            return Command::FAILURE;
        }

        $io->title('LLM-Eval Runner');

        // Load the evaluation file
        $eval = $this->loadEvaluationFile($file);

        if ($eval === null) {
            $io->error('Evaluation file must return an LlmEval instance');

            return Command::FAILURE;
        }

        // Run the evaluation
        $parallel = (bool) $input->getOption('parallel');
        $concurrencyOption = $input->getOption('concurrency');
        $concurrency = is_string($concurrencyOption) ? (int) $concurrencyOption : 0;
        $formatOption = $input->getOption('format');
        $format = is_string($formatOption) ? $formatOption : 'text';

        $io->section('Running evaluations...');

        $startTime = microtime(true);
        $result = $this->runEvaluation($eval, $parallel, $concurrency, $io);
        $duration = microtime(true) - $startTime;

        if ($result === null) {
            $io->error('Failed to run evaluation');

            return Command::FAILURE;
        }

        // Output results
        if ($format === 'json') {
            $this->outputJson($result, $output);
        } else {
            $this->outputText($result, $io, $duration);
        }

        return $result->passed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Load and execute the evaluation file.
     */
    private function loadEvaluationFile(string $file): ?LlmEval
    {
        $result = require $file;

        if ($result instanceof LlmEval) {
            return $result;
        }

        return null;
    }

    /**
     * Run the evaluation (sequential or parallel).
     */
    private function runEvaluation(
        LlmEval $eval,
        bool $parallel,
        int $concurrency,
        SymfonyStyle $io,
    ): ?SuiteResult {
        try {
            if ($parallel) {
                $io->text('Mode: <info>parallel</info>' . ($concurrency > 0 ? " (concurrency: {$concurrency})" : ''));

                return $eval->runAllParallel($concurrency);
            }

            $io->text('Mode: <info>sequential</info>');

            return $eval->runAll();
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return null;
        }
    }

    /**
     * Output results as text.
     */
    private function outputText(SuiteResult $result, SymfonyStyle $io, float $duration): void
    {
        $io->newLine();

        // Show individual results
        foreach ($result->results as $r) {
            $status = $r->passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $io->writeln("  {$status} {$r->name}");

            if (!$r->passed) {
                foreach ($r->assertionResults as $assertion) {
                    if (!$assertion->passed) {
                        $io->writeln("       <fg=yellow>→ {$assertion->message}</>");
                    }
                }
            }
        }

        $io->newLine();

        // Summary
        $io->section('Summary');

        $passedCount = $result->passedCount();
        $failedCount = $result->failedCount();
        $total = $result->totalCount();

        $io->definitionList(
            ['Total' => (string) $total],
            ['Passed' => "<fg=green>{$passedCount}</>"],
            ['Failed' => $failedCount > 0 ? "<fg=red>{$failedCount}</>" : '0'],
            ['Pass Rate' => $result->passRatePercent()],
            ['Duration' => sprintf('%.2fs', $duration)],
        );

        if ($result->passed) {
            $io->success('All evaluations passed!');
        } else {
            $io->error("{$failedCount} evaluation(s) failed.");
        }
    }

    /**
     * Output results as JSON.
     */
    private function outputJson(SuiteResult $result, OutputInterface $output): void
    {
        $data = [
            'name' => $result->name,
            'passed' => $result->passed,
            'summary' => [
                'total' => $result->totalCount(),
                'passed' => $result->passedCount(),
                'failed' => $result->failedCount(),
                'pass_rate' => $result->passRatePercent(),
            ],
            'results' => array_map(fn (Result $r) => [
                'name' => $r->name,
                'passed' => $r->passed,
                'response' => $r->response->text,
                'assertions' => array_map(fn (AssertionResult $a) => [
                    'description' => $a->description,
                    'passed' => $a->passed,
                    'message' => $a->message,
                ], $r->assertionResults),
            ], $result->results),
        ];

        $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT));
    }
}
