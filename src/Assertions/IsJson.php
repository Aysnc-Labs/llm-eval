<?php

/**
 * IsJson assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Override;

/**
 * Asserts that the text is valid JSON.
 */
readonly class IsJson implements AssertionInterface
{
    #[Override]
    public function check(string $text): AssertionResult
    {
        // PHP 8.3 native json_validate
        if (json_validate($text)) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            'Text is not valid JSON',
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Is valid JSON';
    }
}
