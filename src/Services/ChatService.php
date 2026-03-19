<?php

namespace AgenticMorf\FluxUIChat\Services;

use AgenticMorf\FluxUIChat\Agents\AnonymousChatAgent;
use AgenticMorf\FluxUIChat\Agents\ChatAgent;
use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use AgenticMorf\LaravelAIModelManager\Services\ModelResolver;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Streaming\Events\TextDelta;
use Maize\Markable\Models\Bookmark;
use Maize\Markable\Models\Reaction;
use OpenTelemetry\API\Trace\StatusCode;

class ChatService
{
    public function __construct() {}

    /**
     * Stream AI response with optional RAG context.
     *
     * @param  callable(string|null): void  $onComplete  Called when stream finishes with new conversationId (if created)
     * @param  string|null  $model  Model name (e.g. from ai_models.model_name) to use; null uses agent default
     * @return \Generator<string>
     */
    public function streamResponse(
        string $message,
        ?Authenticatable $user = null,
        ?string $conversationId = null,
        ?callable $onComplete = null,
        ?string $model = null
    ): \Generator {
        $batchSize = ChatAgent::$streamBatchChars;
        $buffer = '';

        $span = class_exists(Tracer::class)
            ? Tracer::newSpan('chat.streamResponse')->start()
            : null;
        $span?->setAttribute('chat.conversation_id', $conversationId ?? 'new');
        $span?->setAttribute('chat.user_id', (string) ($user?->getAuthIdentifier() ?? 'anon'));

        try {
            Log::info('ChatService::streamResponse start', [
                'conversation_id' => $conversationId,
                'user_id' => $user?->getAuthIdentifier(),
                'model' => $model,
            ]);

            if ($conversationId && $user) {
                $this->validateConversationOwnership($conversationId, $user);
            }

            $resolvedModel = $this->resolveModelForChat($model, $user);
            Log::info('ChatService::streamResponse agent setup', [
                'resolved_model' => $resolvedModel,
            ]);

            $contextClass = config('fluxui-chat.conversation_context_class');
            if ($contextClass && class_exists($contextClass)) {
                app()->instance($contextClass, new $contextClass($conversationId, $user));
            }

            $agent = $conversationId
                ? ChatAgent::make()->continue($conversationId, as: $user)
                : ChatAgent::make()->forUser($user);

            $stream = $agent->stream($message, [], null, $resolvedModel);

            if ($onComplete) {
                $stream->then(function ($response) use ($onComplete, $conversationId) {
                    $onComplete($response->conversationId ?? $conversationId);
                });
            }

            $eventCount = 0;
            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $eventCount++;
                    $delta = $event->delta;
                    if ($batchSize > 0) {
                        $buffer .= $delta;
                        if (strlen($buffer) >= $batchSize) {
                            yield $buffer;
                            $buffer = '';
                        }
                    } else {
                        yield $delta;
                    }
                }
            }

            if ($batchSize > 0 && $buffer !== '') {
                yield $buffer;
            }

            Log::info('ChatService::streamResponse complete', [
                'conversation_id' => $conversationId,
                'event_count' => $eventCount ?? 0,
                'total_yielded' => strlen($buffer) + ($eventCount ?? 0) * $batchSize,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChatService::streamResponse failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $span?->recordException($e);
            $span?->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span?->end();
        }
    }

    /**
     * Stream anonymous AI response: no RAG, no memory, no conversation persistence.
     * Uses AnonymousChatAgent with in-session conversation context.
     *
     * @param  iterable<object{role: string, content: string}>  $conversationHistory  Prior messages in this session (for context only; not persisted).
     * @return \Generator<string>
     */
    public function streamAnonymousResponse(
        string $message,
        ?string $model = null,
        ?Authenticatable $user = null,
        iterable $conversationHistory = []
    ): \Generator {
        $batchSize = ChatAgent::$streamBatchChars;
        $buffer = '';

        $span = class_exists(Tracer::class)
            ? Tracer::newSpan('chat.streamAnonymousResponse')->start()
            : null;
        $span?->setAttribute('chat.user_id', (string) ($user?->getAuthIdentifier() ?? 'anon'));

        try {
            Log::info('ChatService::streamAnonymousResponse start', [
                'user_id' => $user?->getAuthIdentifier(),
                'model' => $model,
                'prior_messages' => is_countable($conversationHistory) ? count($conversationHistory) : 0,
            ]);

            [$resolvedProvider, $resolvedModel] = $this->resolveProviderAndModelForChat($model, $user);

            $agent = AnonymousChatAgent::forSession($conversationHistory);
            $stream = $agent->stream($message, [], $resolvedProvider, $resolvedModel);

            $eventCount = 0;
            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $eventCount++;
                    $delta = $event->delta;
                    if ($batchSize > 0) {
                        $buffer .= $delta;
                        if (strlen($buffer) >= $batchSize) {
                            yield $buffer;
                            $buffer = '';
                        }
                    } else {
                        yield $delta;
                    }
                }
            }

            if ($batchSize > 0 && $buffer !== '') {
                yield $buffer;
            }

            Log::info('ChatService::streamAnonymousResponse complete', [
                'event_count' => $eventCount ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChatService::streamAnonymousResponse failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $span?->recordException($e);
            $span?->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span?->end();
        }
    }

    /**
     * Non-streaming: drains stream and returns full text.
     */
    public function getResponse(
        string $message,
        ?Authenticatable $user = null,
        ?string $conversationId = null,
        ?string $model = null
    ): string {
        $buffer = '';
        foreach ($this->streamResponse($message, $user, $conversationId, null, $model) as $delta) {
            $buffer .= $delta;
        }

        return $buffer;
    }

    /**
     * Resolve provider and model for chat from ai-model-manager (agent/team/user config).
     * Returns [provider, model] where provider is the driver name (e.g. 'ollama', 'openai').
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function resolveProviderAndModelForChat(?string $model, ?Authenticatable $user): array
    {
        if ($model === null || $model === '') {
            return [null, null];
        }

        if (! class_exists(ModelResolver::class)) {
            return [config('ai.default'), $model];
        }

        $resolver = app(ModelResolver::class);
        $userId = $user?->getAuthIdentifier();
        $userId = $userId !== null ? (string) $userId : null;
        $teamId = session('current_team_id');
        $groupId = session('current_group_id');
        $agentClass = ChatAgent::class;

        $config = $resolver->resolve($model, $agentClass, $userId, $teamId, $groupId);

        return [
            $config['driver'] ?? config('ai.default'),
            $config['model'] ?? $model,
        ];
    }

    protected function resolveModelForChat(?string $model, ?Authenticatable $user): ?string
    {
        [, $resolvedModel] = $this->resolveProviderAndModelForChat($model, $user);

        return $resolvedModel;
    }

    /**
     * Get accessible conversations for the index, with filters and pagination.
     *
     * @param  array{search?: string, date_range?: string, model?: string, sort?: string}  $filters
     */
    public function getAccessibleConversations(Authenticatable $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $userId = (string) $user->getAuthIdentifier();

        $query = DB::table('agent_conversations')
            ->select([
                'agent_conversations.id',
                'agent_conversations.title',
                'agent_conversations.updated_at',
            ])
            ->selectRaw('(SELECT COUNT(*) FROM agent_conversation_messages WHERE conversation_id = agent_conversations.id) AS message_count');

        $shareableType = config('markable.user_model');
        if (config('database.default') === 'pgsql') {
            $query->whereRaw('id IN (SELECT * FROM get_accessible_conversation_ids(?) AS t(id))', [$userId]);
        } else {
            $own = DB::table('agent_conversations')->where('user_id', $userId)->pluck('id');
            $shared = $shareableType
                ? DB::table('conversation_shares')
                    ->where('shareable_type', $shareableType)
                    ->where('shareable_id', $userId)
                    ->pluck('conversation_id')
                : collect();
            $ids = $own->merge($shared)->unique()->values()->all();
            $query->whereIn('id', $ids);
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        $dateRange = $filters['date_range'] ?? null;
        if ($dateRange === '7d') {
            $query->where('agent_conversations.updated_at', '>=', now()->subDays(7));
        } elseif ($dateRange === '30d') {
            $query->where('agent_conversations.updated_at', '>=', now()->subDays(30));
        } elseif ($dateRange === '90d') {
            $query->where('agent_conversations.updated_at', '>=', now()->subDays(90));
        }

        if (! empty($filters['model'])) {
            $modelFilter = $filters['model'];
            $driver = config('database.connections.'.config('database.default').'.driver', 'sqlite');
            if (config('database.default') === 'pgsql') {
                $query->whereExists(function ($q) use ($modelFilter) {
                    $q->select(DB::raw(1))
                        ->from('agent_conversation_messages')
                        ->whereColumn('agent_conversation_messages.conversation_id', 'agent_conversations.id')
                        ->whereRaw("meta::jsonb->>'model' = ?", [$modelFilter]);
                });
            } elseif ($driver === 'sqlite') {
                $query->whereExists(function ($q) use ($modelFilter) {
                    $q->select(DB::raw(1))
                        ->from('agent_conversation_messages')
                        ->whereColumn('agent_conversation_messages.conversation_id', 'agent_conversations.id')
                        ->whereRaw("json_extract(meta, '$.model') = ?", [$modelFilter]);
                });
            } else {
                $query->whereExists(function ($q) use ($modelFilter) {
                    $q->select(DB::raw(1))
                        ->from('agent_conversation_messages')
                        ->whereColumn('agent_conversation_messages.conversation_id', 'agent_conversations.id')
                        ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(meta, "$.model")) = ?', [$modelFilter]);
                });
            }
        }

        $sort = $filters['sort'] ?? 'updated_at_desc';
        if ($sort === 'title_asc') {
            $query->orderBy('title');
        } elseif ($sort === 'title_desc') {
            $query->orderByDesc('title');
        } elseif ($sort === 'created_at_asc') {
            $query->orderBy('created_at');
        } elseif ($sort === 'created_at_desc') {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('updated_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get distinct model names from conversation messages for the filter dropdown.
     *
     * @return array<string>
     */
    public function getDistinctModelsForUser(Authenticatable $user): array
    {
        $userId = (string) $user->getAuthIdentifier();

        $shareableType = config('markable.user_model');
        if (config('database.default') === 'pgsql') {
            $ids = DB::select('SELECT * FROM get_accessible_conversation_ids(?) AS t(id)', [$userId]);
            $conversationIds = array_map(fn ($r) => $r->id, $ids);
        } else {
            $own = DB::table('agent_conversations')->where('user_id', $userId)->pluck('id');
            $shared = $shareableType
                ? DB::table('conversation_shares')
                    ->where('shareable_type', $shareableType)
                    ->where('shareable_id', $userId)
                    ->pluck('conversation_id')
                : collect();
            $conversationIds = $own->merge($shared)->unique()->values()->all();
        }

        if ($conversationIds === []) {
            return [];
        }

        $driver = config('database.connections.'.config('database.default').'.driver', 'sqlite');

        if (config('database.default') === 'pgsql') {
            $rows = DB::table('agent_conversation_messages')
                ->whereIn('conversation_id', $conversationIds)
                ->whereNotNull('meta')
                ->where('meta', '!=', '')
                ->selectRaw("DISTINCT meta::jsonb->>'model' AS model")
                ->pluck('model')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        } elseif ($driver === 'sqlite') {
            $rows = DB::table('agent_conversation_messages')
                ->whereIn('conversation_id', $conversationIds)
                ->whereNotNull('meta')
                ->where('meta', '!=', '')
                ->selectRaw("DISTINCT json_extract(meta, '$.model') AS model")
                ->pluck('model')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        } else {
            $rows = DB::table('agent_conversation_messages')
                ->whereIn('conversation_id', $conversationIds)
                ->whereNotNull('meta')
                ->where('meta', '!=', '')
                ->selectRaw('DISTINCT JSON_UNQUOTE(JSON_EXTRACT(meta, "$.model")) AS model')
                ->pluck('model')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return $rows;
    }

    /**
     * Get conversation title. Returns null if not found or not accessible.
     */
    public function getConversationTitle(?string $conversationId, Authenticatable $user): ?string
    {
        if ($conversationId === null || $conversationId === '') {
            return null;
        }

        try {
            $this->validateConversationOwnership($conversationId, $user);
        } catch (AuthorizationException $e) {
            return null;
        }

        $row = DB::table('agent_conversations')->where('id', $conversationId)->first();

        return $row?->title ?? null;
    }

    /**
     * Load messages for a conversation with metadata (created_at, model_name, user_name, id).
     * Validates ownership before returning.
     *
     * @return array<int, object{id: string|null, role: string, content: string, created_at: Carbon, name: string}>
     */
    public function getMessagesForConversation(string $conversationId, Authenticatable $user): array
    {
        $this->validateConversationOwnership($conversationId, $user);

        $userName = $user->name ?? $user->email ?? 'You';

        $rows = AgentConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();

        return $rows->map(function ($m) use ($user, $userName) {
            $meta = is_array($m->meta) ? $m->meta : (json_decode($m->meta ?? '[]', true) ?? []);
            $rawModel = $meta['model'] ?? null;
            $displayName = $m->role === 'user'
                ? $userName
                : $this->resolveModelDisplayName($rawModel, $user);

            return (object) [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content ?? '',
                'created_at' => Carbon::parse($m->created_at),
                'name' => $displayName,
            ];
        })->values()->all();
    }

    /**
     * Get mark state (thumbs up/down, bookmarked) for messages, keyed by message ID.
     *
     * @param  array<string>  $messageIds
     * @return array{thumbs_up: array<string>, thumbs_down: array<string>, bookmarked: array<string>}
     */
    public function getMarkStateForMessages(array $messageIds, Authenticatable $user): array
    {
        $userModel = config('markable.user_model');
        if ($messageIds === [] || ! $userModel || ! $user instanceof $userModel) {
            return ['thumbs_up' => [], 'thumbs_down' => [], 'bookmarked' => []];
        }

        $userId = (string) $user->getAuthIdentifier();
        $modelClass = AgentConversationMessage::class;

        $reactions = Reaction::query()
            ->where('markable_type', $modelClass)
            ->whereIn('markable_id', $messageIds)
            ->where('user_id', $userId)
            ->whereIn('value', ['thumbs_up', 'thumbs_down'])
            ->get(['markable_id', 'value']);

        $bookmarks = Bookmark::query()
            ->where('markable_type', $modelClass)
            ->whereIn('markable_id', $messageIds)
            ->where('user_id', $userId)
            ->pluck('markable_id')
            ->all();

        $thumbsUp = $reactions->where('value', 'thumbs_up')->pluck('markable_id')->values()->all();
        $thumbsDown = $reactions->where('value', 'thumbs_down')->pluck('markable_id')->values()->all();

        return [
            'thumbs_up' => $thumbsUp,
            'thumbs_down' => $thumbsDown,
            'bookmarked' => $bookmarks,
        ];
    }

    /**
     * Get paginated bookmarked messages for the user, with conversation context.
     * Only returns messages from conversations the user can access.
     *
     * @return LengthAwarePaginator<int, object{id: string, role: string, content: string, created_at: Carbon, name: string, conversation_id: string, conversation_title: string|null}>
     */
    public function getBookmarkedMessages(Authenticatable $user, int $perPage = 15): LengthAwarePaginator
    {
        $userId = (string) $user->getAuthIdentifier();

        $conversationIds = $this->getAccessibleConversationIdsForUser($userId);

        if ($conversationIds === []) {
            return new LengthAwarePaginatorConcrete(collect(), 0, $perPage);
        }

        $userName = $user->name ?? $user->email ?? 'You';

        $query = Bookmark::query()
            ->where('markable_bookmarks.markable_type', AgentConversationMessage::class)
            ->where('markable_bookmarks.user_id', $userId)
            ->whereIn('markable_id', function ($q) use ($conversationIds) {
                $q->select('id')
                    ->from('agent_conversation_messages')
                    ->whereIn('conversation_id', $conversationIds);
            })
            ->join('agent_conversation_messages as acm', function ($join) {
                $join->on('markable_bookmarks.markable_id', '=', 'acm.id')
                    ->where('markable_bookmarks.markable_type', '=', AgentConversationMessage::class);
            })
            ->join('agent_conversations as ac', 'acm.conversation_id', '=', 'ac.id')
            ->select([
                'acm.id as message_id',
                'acm.conversation_id',
                'acm.role',
                'acm.content',
                'acm.meta',
                'acm.created_at',
                'ac.title as conversation_title',
            ])
            ->orderByDesc('acm.created_at');

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($row) use ($user, $userName): object {
            $meta = is_array($row->meta) ? $row->meta : (json_decode($row->meta ?? '[]', true) ?? []);
            $rawModel = $meta['model'] ?? null;
            $displayName = $row->role === 'user'
                ? $userName
                : $this->resolveModelDisplayName($rawModel, $user);

            return (object) [
                'id' => $row->message_id,
                'role' => $row->role,
                'content' => $row->content ?? '',
                'created_at' => Carbon::parse($row->created_at),
                'name' => $displayName,
                'conversation_id' => $row->conversation_id,
                'conversation_title' => $row->conversation_title ?? null,
            ];
        });

        return new LengthAwarePaginatorConcrete(
            $items->values(),
            $paginator->total(),
            $perPage,
            $paginator->currentPage()
        );
    }

    /**
     * @return array<string>
     */
    protected function getAccessibleConversationIdsForUser(string $userId): array
    {
        if (config('database.default') === 'pgsql') {
            $rows = DB::select('SELECT * FROM get_accessible_conversation_ids(?) AS t(id)', [$userId]);

            return array_map(fn ($r) => $r->id, $rows);
        }

        $shareableType = config('markable.user_model');
        $own = DB::table('agent_conversations')->where('user_id', $userId)->pluck('id');
        $shared = $shareableType
            ? DB::table('conversation_shares')
                ->where('shareable_type', $shareableType)
                ->where('shareable_id', $userId)
                ->pluck('conversation_id')
            : collect();

        return $own->merge($shared)->unique()->values()->all();
    }

    /**
     * Resolve raw model string (e.g. qwen3.5:cloud) to user-facing display name (e.g. MorfLLM).
     */
    protected function resolveModelDisplayName(?string $rawModel, Authenticatable $user): string
    {
        if ($rawModel === null || $rawModel === '') {
            return 'Assistant';
        }

        $aiModelClass = 'AgenticMorf\\LaravelAIModelManager\\Models\\AiModel';
        $userClass = config('markable.user_model') ?? config('ai-model-manager.owner_types.user');
        if (! class_exists($aiModelClass) || ! $userClass || ! $user instanceof $userClass) {
            return $rawModel;
        }

        $aiModel = $aiModelClass::query()
            ->accessibleBy($user, ChatAgent::class)
            ->where('model', $rawModel)
            ->first();

        return $aiModel?->model_name ?? $rawModel;
    }

    /**
     * Clone a conversation and all its messages into a new conversation.
     * Optionally clones conversation_shares (Team, Group, User) to the new conversation.
     *
     * @param  string|null  $customTitle  Optional custom title; if empty, uses original title + " (copy)"
     * @return string The new conversation ID
     *
     * @throws AuthorizationException
     */
    public function cloneConversation(string $sourceConversationId, Authenticatable $user, bool $clonePermissions, ?string $customTitle = null): string
    {
        $this->validateConversationOwnership($sourceConversationId, $user);

        $userId = (string) $user->getAuthIdentifier();
        $source = DB::table('agent_conversations')->where('id', $sourceConversationId)->first();
        if (! $source) {
            throw new AuthorizationException('Conversation not found.');
        }

        $title = trim((string) $customTitle) !== ''
            ? Str::limit(trim($customTitle), 255)
            : Str::limit($source->title, 40).' (copy)';
        $newConversationId = app(ConversationStore::class)->storeConversation($userId, $title);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $sourceConversationId)
            ->orderBy('created_at')
            ->get();

        foreach ($messages as $msg) {
            DB::table('agent_conversation_messages')->insert([
                'id' => 'msg_'.Str::ulid(),
                'conversation_id' => $newConversationId,
                'user_id' => $msg->user_id,
                'agent' => $msg->agent,
                'role' => $msg->role,
                'content' => $msg->content,
                'attachments' => $msg->attachments,
                'tool_calls' => $msg->tool_calls,
                'tool_results' => $msg->tool_results,
                'usage' => $msg->usage,
                'meta' => $msg->meta,
                'created_at' => $msg->created_at,
                'updated_at' => $msg->updated_at,
            ]);
        }

        if ($clonePermissions) {
            $shares = DB::table('conversation_shares')
                ->where('conversation_id', $sourceConversationId)
                ->get();

            foreach ($shares as $share) {
                DB::table('conversation_shares')->insert([
                    'conversation_id' => $newConversationId,
                    'shareable_type' => $share->shareable_type,
                    'shareable_id' => $share->shareable_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $newConversationId;
    }

    /**
     * Fork a conversation at a specific message: create a new conversation with messages
     * up to and including the given messageId. Optionally clones conversation_shares.
     *
     * @param  string|null  $customTitle  Optional custom title; if empty, uses original title + " (copy)"
     * @return string The new conversation ID
     *
     * @throws AuthorizationException
     */
    public function forkConversationAtMessage(string $sourceConversationId, string $atMessageId, Authenticatable $user, bool $clonePermissions, ?string $customTitle = null): string
    {
        $this->validateConversationOwnership($sourceConversationId, $user);

        $userId = (string) $user->getAuthIdentifier();
        $source = DB::table('agent_conversations')->where('id', $sourceConversationId)->first();
        if (! $source) {
            throw new AuthorizationException('Conversation not found.');
        }

        $title = trim((string) $customTitle) !== ''
            ? Str::limit(trim($customTitle), 255)
            : Str::limit($source->title, 40).' (copy)';
        $newConversationId = app(ConversationStore::class)->storeConversation($userId, $title);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $sourceConversationId)
            ->orderBy('created_at')
            ->get();

        foreach ($messages as $msg) {
            DB::table('agent_conversation_messages')->insert([
                'id' => 'msg_'.Str::ulid(),
                'conversation_id' => $newConversationId,
                'user_id' => $msg->user_id,
                'agent' => $msg->agent,
                'role' => $msg->role,
                'content' => $msg->content,
                'attachments' => $msg->attachments,
                'tool_calls' => $msg->tool_calls,
                'tool_results' => $msg->tool_results,
                'usage' => $msg->usage,
                'meta' => $msg->meta,
                'created_at' => $msg->created_at,
                'updated_at' => $msg->updated_at,
            ]);
            if ($msg->id === $atMessageId) {
                break;
            }
        }

        if ($clonePermissions) {
            $shares = DB::table('conversation_shares')
                ->where('conversation_id', $sourceConversationId)
                ->get();

            foreach ($shares as $share) {
                DB::table('conversation_shares')->insert([
                    'conversation_id' => $newConversationId,
                    'shareable_type' => $share->shareable_type,
                    'shareable_id' => $share->shareable_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $newConversationId;
    }

    /**
     * @throws AuthorizationException
     */
    public function validateConversationOwnership(string $conversationId, Authenticatable $user): void
    {
        $userId = (string) $user->getAuthIdentifier();

        if (config('database.default') === 'pgsql') {
            $accessible = DB::selectOne(
                'SELECT 1 FROM agent_conversations WHERE id = ? AND id IN (SELECT * FROM get_accessible_conversation_ids(?) AS t(id))',
                [$conversationId, $userId]
            );
            if (! $accessible) {
                throw new AuthorizationException('Conversation not found.');
            }

            return;
        }

        $owns = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        if (! $owns) {
            throw new AuthorizationException('Conversation not found.');
        }
    }
}
