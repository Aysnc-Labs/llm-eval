<?php

/**
 * MinLength assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Override;

/**
 * Asserts that the text meets a minimum length.
 */
readonly class MinLength implements AssertionInterface
{
    /**
     * @param int $minLength The minimum required length.
     */
    public function __construct(
        private int $minLength,
    ) {
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        $length = mb_strlen($text);

        if ($length >= $this->minLength) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Text length is %d, below minimum of %d', $length, $this->minLength),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Minimum length of %d characters', $this->minLength);
    }
}
