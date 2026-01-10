<?php

/**
 * CLI Application for LLM-Eval.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Console;

use Symfony\Component\Console\Application as BaseApplication;

/**
 * Main CLI application.
 */
class Application extends BaseApplication
{
    private const string NAME = 'LLM-Eval';
    private const string VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->add(new RunCommand());
        $this->add(new InitCommand());
        $this->add(new CacheClearCommand());
    }
}
