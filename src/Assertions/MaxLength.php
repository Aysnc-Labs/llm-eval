<?php

/**
 * MaxLength assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Override;

/**
 * Asserts that the text does not exceed a maximum length.
 */
readonly class MaxLength implements AssertionInterface
{
    /**
     * @param int $maxLength The maximum allowed length.
     */
    public function __construct(
        private int $maxLength,
    ) {
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        $length = mb_strlen($text);

        if ($length <= $this->maxLength) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Text length is %d, exceeds maximum of %d', $length, $this->maxLength),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Maximum length of %d characters', $this->maxLength);
    }
}
