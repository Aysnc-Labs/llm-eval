<?php

/**
 * Interface for assertions that need access to the full Conversation.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Assertions;

use Aysnc\AI\LlmEval\Conversation\Conversation;

/**
 * Extends AssertionInterface for assertions that need the full Conversation.
 *
 * Some assertions (like turn count or cross-turn tool usage) need more than
 * just the final response text. This interface allows assertions to receive
 * the complete Conversation object before check() is called.
 *
 * Pattern: ConversationEval checks if assertion implements this interface
 * and calls withConversation() before check(). The assertion stores the
 * Conversation and uses it during check().
 */
interface ConversationAwareAssertion extends AssertionInterface
{
    /**
     * Inject the full Conversation for assertions that need it.
     *
     * Called by ConversationEval before check() for assertions implementing
     * this interface. Returns a new instance with the Conversation set (immutable pattern).
     *
     * @param Conversation $conversation The full conversation.
     *
     * @return self A new instance with the Conversation.
     */
    public function withConversation(Conversation $conversation): self;
}
