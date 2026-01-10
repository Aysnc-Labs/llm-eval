<?php

/**
 * CLI command to clear the cache.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Clear the LLM response cache.
 *
 * Usage:
 *   llm-eval cache:clear
 */
#[AsCommand(
    name: 'cache:clear',
    description: 'Clear the LLM response cache',
)]
class CacheClearCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workingDir = (string) getcwd();

        $config = Config::load($workingDir);
        $cachePath = $config->getCachePath();

        if ($cachePath === null) {
            $io->warning('No cache configured. Nothing to clear.');

            return Command::SUCCESS;
        }

        if (!is_dir($cachePath)) {
            $io->info('Cache directory does not exist. Nothing to clear.');

            return Command::SUCCESS;
        }

        $files = glob($cachePath . '/*.json');
        $count = is_array($files) ? count($files) : 0;

        if ($count === 0) {
            $io->info('Cache is already empty.');

            return Command::SUCCESS;
        }

        if (is_array($files)) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        $io->success("Cleared {$count} cached response(s) from {$cachePath}");

        return Command::SUCCESS;
    }
}
