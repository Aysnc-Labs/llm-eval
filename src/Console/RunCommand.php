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
 *   llm-eval run                     # Run all evals from llm-eval.php config
 *   llm-eval run eval.php            # Run specific file
 *   llm-eval run --parallel          # Run all evals in parallel
 *   llm-eval run eval.php --format=json
 */
#[AsCommand(
    name: 'run',
    description: 'Run evaluation file(s)',
)]
class RunCommand extends Command
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'The evaluation file to run (omit to run all from config)')
            ->addOption('parallel', 'p', InputOption::VALUE_NONE, 'Run evaluations in parallel')
            ->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Max concurrent requests (with --parallel)', '0')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (text, json)', 'text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $workingDir = (string) getcwd();

        // Load config
        $config = Config::load($workingDir);

        // Determine which files to run
        if (is_string($file) && $file !== '') {
            // Specific file provided
            if (!file_exists($file)) {
                $io->error("Evaluation file not found: {$file}");

                return Command::FAILURE;
            }
            $evalFiles = [$file];
        } else {
            // No file - discover from config
            if (!Config::exists($workingDir)) {
                $io->error('No file specified and no llm-eval.php config found.');
                $io->text('Either provide a file: <info>llm-eval run myeval.php</info>');
                $io->text('Or create a config: <info>llm-eval init --config</info>');

                return Command::FAILURE;
            }

            $evalFiles = $config->discoverEvalFiles();

            if (count($evalFiles) === 0) {
                $io->error("No eval files found in: {$config->getDirectory()}");

                return Command::FAILURE;
            }
        }

        $io->title('LLM-Eval Runner');
        $io->text(sprintf('Found <info>%d</info> eval file(s)', count($evalFiles)));

        // Parse options (CLI flags override config)
        $parallel = $input->getOption('parallel') || $config->isParallel();
        $concurrencyOption = $input->getOption('concurrency');
        $concurrency = is_string($concurrencyOption) && $concurrencyOption !== '0'
            ? (int) $concurrencyOption
            : $config->getConcurrency();
        $formatOption = $input->getOption('format');
        $format = is_string($formatOption) ? $formatOption : 'text';

        $io->section('Running evaluations...');
        $io->text('Mode: <info>' . ($parallel ? 'parallel' : 'sequential') . '</info>'
            . ($parallel && $concurrency > 0 ? " (concurrency: {$concurrency})" : ''));

        $startTime = microtime(true);

        // Run all eval files and collect results
        $allResults = [];
        $hasFailure = false;

        foreach ($evalFiles as $evalFile) {
            $io->text("  Running <comment>{$evalFile}</comment>...");

            $eval = $this->loadEvaluationFile($evalFile);

            if ($eval === null) {
                $io->warning("  Skipping {$evalFile} - must return an LlmEval instance");
                continue;
            }

            // Inject default provider if eval doesn't have one
            if (!$eval->hasProvider() && $config->getProvider() !== null) {
                $eval->provider($config->getProvider());
            }

            $result = $this->runEvaluation($eval, $parallel, $concurrency, $io);

            if ($result === null) {
                $hasFailure = true;
                continue;
            }

            $allResults[] = $result;

            if (!$result->passed) {
                $hasFailure = true;
            }
        }

        $duration = microtime(true) - $startTime;

        if (count($allResults) === 0) {
            $io->error('No evaluations were run successfully');

            return Command::FAILURE;
        }

        // Merge all results into one
        $mergedResult = $this->mergeResults($allResults);

        // Output results
        if ($format === 'json') {
            $this->outputJson($mergedResult, $output);
        } else {
            $this->outputText($mergedResult, $io, $duration);
        }

        return $mergedResult->passed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Load and execute the evaluation file.
     */
    private function loadEvaluationFile(string $file): ?LlmEval
    {
        try {
            $result = require $file;

            if ($result instanceof LlmEval) {
                return $result;
            }
        } catch (Throwable $e) {
            // Will return null
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
                return $eval->runAllParallel($concurrency);
            }

            return $eval->runAll();
        } catch (Throwable $e) {
            $io->warning("    Error: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Merge multiple SuiteResults into one.
     *
     * @param array<SuiteResult> $results
     */
    private function mergeResults(array $results): SuiteResult
    {
        if (count($results) === 1) {
            return $results[0];
        }

        $allIndividualResults = [];
        $names = [];

        foreach ($results as $suiteResult) {
            $names[] = $suiteResult->name;
            foreach ($suiteResult->results as $result) {
                $allIndividualResults[] = $result;
            }
        }

        return SuiteResult::fromResults(
            implode(' + ', $names),
            $allIndividualResults
        );
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
                // Show actual response text (truncated if too long)
                $actualText = $r->response->text;
                $displayText = strlen($actualText) > 100
                    ? substr($actualText, 0, 100) . '...'
                    : $actualText;
                $displayText = str_replace("\n", ' ', $displayText);
                $io->writeln("       <fg=gray>Got: \"{$displayText}\"</>");

                foreach ($r->assertionResults as $assertion) {
                    if (!$assertion->passed) {
                        $io->writeln("       <fg=yellow>→ {$assertion->message}</>");
                    }
                }
            } elseif ($io->isVerbose()) {
                // In verbose mode, show judge reasoning for passes too
                foreach ($r->assertionResults as $assertion) {
                    if (str_starts_with($assertion->description, 'Judged by LLM') && $assertion->message !== 'Passed') {
                        $io->writeln("       <fg=gray>→ {$assertion->message}</>");
                    }
                }
            }

            // In verbose mode, show tool calls
            if ($io->isVerbose() && $r->response->hasToolCalls()) {
                $toolNames = array_map(
                    fn ($tc) => $tc->name,
                    $r->response->toolCalls
                );
                $toolList = implode(', ', array_unique($toolNames));
                $count = count($r->response->toolCalls);
                $io->writeln("       <fg=cyan>Tools ({$count}): {$toolList}</>");
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
                'tool_calls' => array_map(fn ($tc) => [
                    'name' => $tc->name,
                    'input' => $tc->input,
                ], $r->response->toolCalls),
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
