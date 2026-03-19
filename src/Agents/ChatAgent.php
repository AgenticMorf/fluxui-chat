<?php

namespace AgenticMorf\FluxUIChat\Agents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider(Lab::Ollama)]
class ChatAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    public static string $model = 'llama3.1:8b';

    public static int $maxContextMessages = 20;

    public static int $streamBatchChars = 80;

    public function instructions(): string
    {
        $base = config('fluxui-chat.agent_instructions', 'You are a helpful assistant. Answer questions based on the context provided when available.');

        $user = $this->conversationParticipant();
        if ($user !== null) {
            $name = $user->name ?? $user->email ?? null;
            if ($name !== null && $name !== '') {
                $base .= ' The current user\'s name is '.$name.'.';
                Log::info('ChatAgent: injected user name from profile', [
                    'source' => 'profile',
                    'user_id' => $user->getAuthIdentifier(),
                ]);
            }
        }

        $preloadedKey = config('fluxui-chat.preloaded_memory_key');
        if ($preloadedKey !== null && app()->bound($preloadedKey)) {
            $memory = app($preloadedKey);
            if (is_string($memory) && $memory !== '') {
                $base .= ' '.$memory;
                Log::info('ChatAgent: injected preloaded memory from Cognee', [
                    'source' => 'cognee',
                    'memory_length' => strlen($memory),
                ]);
            }
        }

        return $base;
    }

    /**
     * Get messages from current conversation, or from all user conversations
     * within the cross-conversation window when configured.
     */
    public function messages(): iterable
    {
        $user = $this->conversationParticipant();
        if ($user === null) {
            return [];
        }

        $userId = (string) $user->getAuthIdentifier();
        $limit = $this->maxConversationMessages();
        $windowSeconds = (int) config('fluxui-chat.cross_conversation_window_seconds', 0);

        if ($windowSeconds <= 0 || ! $this->conversationId) {
            if (! $this->conversationId) {
                return [];
            }

            return app(ConversationStore::class)
                ->getLatestConversationMessages($this->conversationId, $limit)
                ->all();
        }

        $conversationIds = $this->getAccessibleConversationIds($userId);
        if ($conversationIds === []) {
            return [];
        }

        $since = now()->subSeconds($windowSeconds);

        $rows = DB::table('agent_conversation_messages')
            ->whereIn('conversation_id', $conversationIds)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['role', 'content']);

        return $rows->map(fn ($m) => new Message($m->role, $m->content ?? ''))->all();
    }

    /**
     * @return array<string>
     */
    protected function getAccessibleConversationIds(string $userId): array
    {
        if (config('database.default') === 'pgsql') {
            $rows = DB::select('SELECT * FROM get_accessible_conversation_ids(?) AS t(id)', [$userId]);

            return array_map(fn ($r) => $r->id, $rows);
        }

        $own = DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->pluck('id')
            ->all();

        $shareableType = config('markable.user_model');
        $shared = $shareableType
            ? DB::table('conversation_shares')
                ->where('shareable_type', $shareableType)
                ->where('shareable_id', $userId)
                ->pluck('conversation_id')
                ->all()
            : [];

        return array_values(array_unique(array_merge($own, $shared)));
    }

    public static function model(): string
    {
        return static::$model;
    }

    protected function maxConversationMessages(): int
    {
        return static::$maxContextMessages;
    }

    /**
     * @return array<object>
     */
    public function middleware(): array
    {
        $classes = config('fluxui-chat.agent_middleware', []);

        return array_map(fn (string $class) => app($class), $classes);
    }

    /**
     * @return array<object>
     */
    public function tools(): array
    {
        $classes = config('fluxui-chat.agent_tools', []);

        $tools = [];
        foreach ($classes as $class) {
            if (is_string($class) && class_exists($class)) {
                $tools[] = app($class);
            }
        }

        return $tools;
    }
}
