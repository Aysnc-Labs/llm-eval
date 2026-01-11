<?php

/**
 * CalledTool assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Providers\Response;
use LogicException;
use Override;

/**
 * Asserts that a specific tool was called.
 *
 * Can optionally assert an exact number of times the tool was called.
 */
readonly class CalledTool implements ResponseAwareAssertion
{
    /**
     * @param string $toolName The name of the tool to check for.
     * @param int|null $times If set, assert this exact number of calls. If null, assert at least one.
     * @param Response|null $response The response to check (injected via withResponse).
     */
    public function __construct(
        private string $toolName,
        private ?int $times = null,
        private ?Response $response = null,
    ) {
    }

    #[Override]
    public function withResponse(Response $response): self
    {
        return new self($this->toolName, $this->times, $response);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->response === null) {
            throw new LogicException('Response not set. CalledTool requires withResponse() to be called first.');
        }

        $matchingCalls = $this->response->getToolCalls($this->toolName);
        $callCount = count($matchingCalls);

        // Check for exact count if specified
        if ($this->times !== null) {
            if ($callCount === $this->times) {
                return AssertionResult::pass($this->getDescription());
            }

            return AssertionResult::fail(
                $this->getDescription(),
                sprintf('Tool "%s" was called %d time(s), expected %d', $this->toolName, $callCount, $this->times),
            );
        }

        // Otherwise just check it was called at least once
        if ($callCount > 0) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Tool "%s" was not called', $this->toolName),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        if ($this->times !== null) {
            return sprintf('Tool "%s" called exactly %d time(s)', $this->toolName, $this->times);
        }

        return sprintf('Tool "%s" was called', $this->toolName);
    }
}
