<?php

/**
 * MatchesRegex assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Override;

/**
 * Asserts that the text matches a regular expression.
 */
readonly class MatchesRegex implements AssertionInterface
{
    /**
     * @param string $pattern The regex pattern to match.
     */
    public function __construct(
        private string $pattern,
    ) {
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if (preg_match($this->pattern, $text) === 1) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Text does not match pattern %s', $this->pattern),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Matches regex %s', $this->pattern);
    }
}
