<?php

namespace AgenticMorf\FluxUIChat\Agents;

use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Agent for anonymous chat: no RAG, no memory, no persistence.
 * Instructions explain that nothing is remembered outside the current session.
 */
#[Provider(Lab::Ollama)]
class AnonymousChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  iterable<int, Message>  $messages  In-session conversation history (not persisted).
     */
    public function __construct(
        protected iterable $messages = []
    ) {}

    /**
     * Create an agent with the given in-session conversation history.
     *
     * @param  iterable<object{role: string, content: string}>  $conversationHistory
     */
    public static function forSession(iterable $conversationHistory): self
    {
        $messages = [];
        foreach ($conversationHistory as $m) {
            $messages[] = new Message($m->role ?? 'user', $m->content ?? '');
        }

        return new self($messages);
    }

    public function instructions(): string
    {
        $base = config('fluxui-chat.anonymous_instructions');

        if ($base !== null && $base !== '') {
            return $base;
        }

        return 'You are a helpful assistant in an anonymous chat session. '
            .'This is an anonymous chat: nothing is saved or remembered outside of this current conversation. '
            .'You cannot access the user\'s name, previous conversations, or any information beyond what they share in this session. '
            .'If asked about their identity or past interactions, explain that you only have access to this current chat and cannot remember anything outside it.';
    }

    public function messages(): iterable
    {
        return $this->messages;
    }

    public function tools(): iterable
    {
        return [];
    }
}
