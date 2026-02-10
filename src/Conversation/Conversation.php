<?php

/**
 * Manages multi-turn conversations with tool execution loops.
 *
 * @package aysnc/llm-eval
 */

declare(strict_types=1);

namespace Aysnc\AI\LlmEval\Conversation;

use Aysnc\AI\LlmEval\Providers\ConversableProviderInterface;
use Aysnc\AI\LlmEval\Providers\Message;
use Aysnc\AI\LlmEval\Providers\Response;
use Aysnc\AI\LlmEval\Providers\ToolExecutorInterface;
use RuntimeException;

/**
 * Orchestrates multi-turn conversations with automatic tool execution.
 *
 * The agentic loop: send a message → get response → if tool calls,
 * execute them and send results back → repeat until the LLM stops
 * calling tools or max turns is reached.
 *
 * Usage:
 *   $conversation = Conversation::make($provider, $executor)
 *       ->withTools($toolDefinitions)
 *       ->withMaxTurns(5);
 *
 *   $response = $conversation->send('What is the weather in Paris?');
 *   $followUp = $conversation->reply('What about Tokyo?');
 */
class Conversation
{
    /** @var array<Message> */
    private array $messages = [];

    /** @var array<Response> */
    private array $responses = [];

    /** @var array<int> Turn number (1-indexed) for each message, parallel to $messages. */
    private array $messageTurns = [];

    private int $currentTurn = 0;

    /** @var array<array<string, mixed>> */
    private array $tools = [];

    private int $maxTurns = 10;

    private int $maxTokens = 1024;

    public function __construct(
        private readonly ConversableProviderInterface $provider,
        private readonly ToolExecutorInterface $executor,
    ) {
    }

    public static function make(
        ConversableProviderInterface $provider,
        ToolExecutorInterface $executor,
    ): self {
        return new self($provider, $executor);
    }

    /**
     * Set the tool definitions to send to the LLM.
     *
     * @param array<array<string, mixed>> $tools Tool definitions in Anthropic format.
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    public function withMaxTurns(int $maxTurns): self
    {
        $this->maxTurns = $maxTurns;

        return $this;
    }

    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Send the initial user message and run the tool loop.
     */
    public function send(string $userMessage): Response
    {
        $this->messages = [];
        $this->responses = [];
        $this->messageTurns = [];
        $this->currentTurn = 1;

        return $this->addUserMessageAndComplete($userMessage);
    }

    /**
     * Continue the conversation with another user message.
     */
    public function reply(string $userMessage): Response
    {
        $this->currentTurn++;

        return $this->addUserMessageAndComplete($userMessage);
    }

    /**
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get messages grouped by turn number (1-indexed keys).
     *
     * @return array<int, array<Message>>
     */
    public function getMessagesByTurn(): array
    {
        $grouped = [];
        foreach ($this->messages as $index => $message) {
            $turn = $this->messageTurns[$index] ?? 1;
            $grouped[$turn][] = $message;
        }

        return $grouped;
    }

    /**
     * @return array<Response>
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    private function addUserMessageAndComplete(string $userMessage): Response
    {
        $this->addMessage(Message::user($userMessage));

        return $this->runToolLoop();
    }

    private function runToolLoop(): Response
    {
        $turns = 0;

        while (true) {
            $response = $this->provider->completeWithMessages(
                $this->messages,
                $this->buildOptions(),
            );
            $this->responses[] = $response;
            $turns++;

            if (! $response->hasToolCalls()) {
                $this->addMessage(Message::fromResponse($response));

                return $response;
            }

            if ($turns >= $this->maxTurns) {
                throw new RuntimeException(
                    "Conversation exceeded max turns ({$this->maxTurns})"
                );
            }

            // Append assistant message with tool calls, execute tools, append results.
            $this->addMessage(Message::fromResponse($response));

            $results = [];
            foreach ($response->toolCalls as $toolCall) {
                $results[] = $this->executor->execute($toolCall);
            }

            $this->addMessage(Message::toolResults($results));
        }
    }

    private function addMessage(Message $message): void
    {
        $this->messages[] = $message;
        $this->messageTurns[] = $this->currentTurn;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(): array
    {
        $options = [
            'max_tokens' => $this->maxTokens,
        ];

        if ($this->tools !== []) {
            $options['tools'] = $this->tools;
        }

        return $options;
    }
}
