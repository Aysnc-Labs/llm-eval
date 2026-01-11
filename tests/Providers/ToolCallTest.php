<?php

/**
 * Tests for the ToolCall class.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Tests\Providers;

use Aysnc\AI\LlmEval\Providers\ToolCall;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ToolCall value object.
 */
class ToolCallTest extends TestCase
{
    /**
     * Test that ToolCall stores all values correctly.
     */
    public function testToolCallStoresValues(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_01A09q90qw90lq917835lqub',
            name: 'get_weather',
            input: ['location' => 'San Francisco', 'unit' => 'celsius'],
        );

        $this->assertSame('toolu_01A09q90qw90lq917835lqub', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['location' => 'San Francisco', 'unit' => 'celsius'], $toolCall->input);
    }

    /**
     * Test default empty input.
     */
    public function testDefaultEmptyInput(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_123',
            name: 'no_params_tool',
        );

        $this->assertSame([], $toolCall->input);
    }

    /**
     * Test hasParam returns true for existing parameter.
     */
    public function testHasParamReturnsTrue(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_123',
            name: 'test',
            input: ['foo' => 'bar'],
        );

        $this->assertTrue($toolCall->hasParam('foo'));
    }

    /**
     * Test hasParam returns false for missing parameter.
     */
    public function testHasParamReturnsFalse(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_123',
            name: 'test',
            input: ['foo' => 'bar'],
        );

        $this->assertFalse($toolCall->hasParam('baz'));
    }

    /**
     * Test hasParam returns true for null values (key exists).
     */
    public function testHasParamReturnsTrueForNullValue(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_123',
            name: 'test',
            input: ['nullable' => null],
        );

        $this->assertTrue($toolCall->hasParam('nullable'));
    }

    /**
     * Test getParam returns the value.
     */
    public function testGetParamReturnsValue(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_123',
            name: 'test',
            input: ['location' => 'New York', 'count' => 5],
        );

        $this->assertSame('New York', $toolCall->getParam('location'));
        $this->assertSame(5, $toolCall->getParam('count'));
    }

    /**
     * Test getParam returns null for missing parameter.
     */
    public function testGetParamReturnsNullForMissing(): void
    {
        $toolCall = new ToolCall(
            id: 'toolu_123',
            name: 'test',
            input: ['foo' => 'bar'],
        );

        $this->assertNull($toolCall->getParam('missing'));
    }
}
