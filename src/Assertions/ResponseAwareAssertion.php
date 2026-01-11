<?php

/**
 * Interface for assertions that need access to the full Response.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Providers\Response;

/**
 * Extends AssertionInterface for assertions that need the full Response.
 *
 * Some assertions (like tool call checks) need more than just the response text.
 * This interface allows assertions to receive the complete Response object
 * before check() is called.
 *
 * Pattern: LlmEval checks if assertion implements this interface and calls
 * withResponse() before check(). The assertion stores the Response and uses
 * it during check().
 */
interface ResponseAwareAssertion extends AssertionInterface
{
    /**
     * Inject the full Response for assertions that need it.
     *
     * Called by LlmEval before check() for assertions implementing this interface.
     * Returns a new instance with the Response set (immutable pattern).
     *
     * @param Response $response The full LLM response.
     *
     * @return self A new instance with the Response.
     */
    public function withResponse(Response $response): self;
}
