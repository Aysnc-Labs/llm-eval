<?php

/**
 * CalledToolCount assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Providers\Response;
use LogicException;
use Override;

/**
 * Asserts the total number of tool calls in the response.
 *
 * This checks the count of ALL tool calls, regardless of which tool was called.
 */
readonly class CalledToolCount implements ResponseAwareAssertion
{
    /**
     * @param int $count The expected total number of tool calls.
     * @param Response|null $response The response to check (injected via withResponse).
     */
    public function __construct(
        private int $count,
        private ?Response $response = null,
    ) {
    }

    #[Override]
    public function withResponse(Response $response): self
    {
        return new self($this->count, $response);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->response === null) {
            throw new LogicException('Response not set. CalledToolCount requires withResponse() to be called first.');
        }

        $actualCount = count($this->response->toolCalls);

        if ($actualCount === $this->count) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Response has %d tool call(s), expected %d', $actualCount, $this->count),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Response has exactly %d tool call(s)', $this->count);
    }
}
