<?php

namespace AgenticMorf\FluxUIChat\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface MemoryContextProvider
{
    /**
     * Get relevant conversation memory context for the given message.
     * When $user is provided, searches across all of the user's accessible conversations (cross-conversation memory).
     * When $user is null, searches only the current conversation.
     *
     * @param  string|null  $conversationId  Current conversation ID (used when user is null)
     * @param  string  $message  The user's message to find relevant context for
     * @param  int  $topK  Maximum number of results to return
     * @param  Authenticatable|null  $user  When provided, enables cross-conversation memory search
     */
    public function getContext(?string $conversationId, string $message, int $topK = 5, ?Authenticatable $user = null): string;
}
