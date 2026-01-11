<?php

/**
 * ToolCallHasParam assertion.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Providers\Response;
use LogicException;
use Override;

/**
 * Asserts that a tool call has a specific parameter.
 *
 * Can optionally assert that the parameter has a specific value.
 * Checks the first matching tool call.
 */
readonly class ToolCallHasParam implements ResponseAwareAssertion
{
    private const UNSET = '__UNSET__';

    /**
     * @param string $toolName The name of the tool to check.
     * @param string $paramName The name of the parameter to check for.
     * @param mixed $expectedValue The expected value (use default to skip value check).
     * @param Response|null $response The response to check (injected via withResponse).
     */
    public function __construct(
        private string $toolName,
        private string $paramName,
        private mixed $expectedValue = self::UNSET,
        private ?Response $response = null,
    ) {
    }

    #[Override]
    public function withResponse(Response $response): self
    {
        return new self($this->toolName, $this->paramName, $this->expectedValue, $response);
    }

    #[Override]
    public function check(string $text): AssertionResult
    {
        if ($this->response === null) {
            throw new LogicException('Response not set. ToolCallHasParam requires withResponse() to be called first.');
        }

        $toolCall = $this->response->getToolCall($this->toolName);

        if ($toolCall === null) {
            return AssertionResult::fail(
                $this->getDescription(),
                sprintf('Tool "%s" was not called', $this->toolName),
            );
        }

        if (!$toolCall->hasParam($this->paramName)) {
            return AssertionResult::fail(
                $this->getDescription(),
                sprintf('Tool "%s" does not have parameter "%s"', $this->toolName, $this->paramName),
            );
        }

        // If we're just checking for param existence, we're done
        if ($this->expectedValue === self::UNSET) {
            return AssertionResult::pass($this->getDescription());
        }

        // Check the value
        $actualValue = $toolCall->getParam($this->paramName);

        if ($actualValue === $this->expectedValue) {
            return AssertionResult::pass($this->getDescription());
        }

        return AssertionResult::fail(
            $this->getDescription(),
            sprintf(
                'Parameter "%s" has value %s, expected %s',
                $this->paramName,
                $this->formatValue($actualValue),
                $this->formatValue($this->expectedValue),
            ),
        );
    }

    #[Override]
    public function getDescription(): string
    {
        if ($this->expectedValue === self::UNSET) {
            return sprintf('Tool "%s" has parameter "%s"', $this->toolName, $this->paramName);
        }

        return sprintf(
            'Tool "%s" has parameter "%s" = %s',
            $this->toolName,
            $this->paramName,
            $this->formatValue($this->expectedValue),
        );
    }

    /**
     * Format a value for display in messages.
     */
    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return sprintf('"%s"', $value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // For objects or other types, use var_export
        return var_export($value, true);
    }
}
