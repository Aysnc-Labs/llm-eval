<?php

/**
 * NotContains assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Override;

/**
 * Asserts that the text does not contain a given substring.
 */
readonly class NotContains implements AssertionInterface
{
    /**
     * @param string $needle The substring that should not be present.
     * @param bool $caseSensitive Whether the search is case-sensitive.
     */
    public function __construct(
        private string $needle,
        private bool $caseSensitive = true,
    ) {
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        $haystack = $this->caseSensitive ? $text : strtolower($text);
        $needle = $this->caseSensitive ? $this->needle : strtolower($this->needle);

        if (!str_contains($haystack, $needle)) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Text contains "%s" but should not', $this->needle),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        $case = $this->caseSensitive ? '' : ' (case-insensitive)';

        return sprintf('Does not contain "%s"%s', $this->needle, $case);
    }
}
