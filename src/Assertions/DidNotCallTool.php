<?php

/**
 * DidNotCallTool assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Providers\Response;
use LogicException;
use Override;

/**
 * Asserts that a specific tool was NOT called.
 *
 * Useful for testing that dangerous or unwanted tools are not invoked.
 */
readonly class DidNotCallTool implements ResponseAwareAssertion
{
    /**
     * @param string $toolName The name of the tool that should not be called.
     * @param Response|null $response The response to check (injected via withResponse).
     */
    public function __construct(
        private string $toolName,
        private ?Response $response = null,
    ) {
    }

    #[Override]
    public function withResponse(Response $response): self
    {
        return new self($this->toolName, $response);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->response === null) {
            throw new LogicException('Response not set. DidNotCallTool requires withResponse() to be called first.');
        }

        $matchingCalls = $this->response->getToolCalls($this->toolName);
        $callCount = count($matchingCalls);

        if ($callCount === 0) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf('Tool "%s" was called %d time(s)', $this->toolName, $callCount),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        return sprintf('Tool "%s" was not called', $this->toolName);
    }
}
