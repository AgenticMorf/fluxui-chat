<?php

namespace AgenticMorf\FluxUIChat\Livewire;

use AgenticMorf\FluxUIChat\Agents\ChatAgent;
use AgenticMorf\FluxUIChat\Contracts\AccessibleBasesProvider;
use AgenticMorf\FluxUIChat\Contracts\ChatUploadService;
use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use AgenticMorf\FluxUIChat\Services\ChatService;
use AgenticMorf\LaravelAIModelManager\Models\AiModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\ConversationStore;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maize\Markable\Models\Bookmark;
use Maize\Markable\Models\Reaction;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Chat')]
class ChatsShow extends Component
{
    use WithFileUploads;

    public string $prompt = '';

    public ?string $question = null;

    public string $answer = '';

    public bool $loading = false;

    public ?string $conversationId = null;

    public array $messages = [];

    public ?string $selectedModel = null;

    public ?string $selectedBaseId = null;

    /** @var UploadedFile[] */
    public array $attachments = [];

    /** @var array<int, array{base_id: string, document_id: string}> */
    public array $pendingAttachments = [];

    public bool $pendingAsk = false;

    public bool $anonymous = false;

    /** @var string Accumulated LLM reply during anonymous stream (not persisted to DB) */
    public string $anonymousAnswer = '';

    public bool $showForkModal = false;

    /** When set, fork at this message; when null, duplicate full conversation. */
    public ?string $forkAtMessageId = null;

    /** @var 'clone'|'private' */
    public string $forkPermissionChoice = 'private';

    /** Optional custom title for the new conversation when duplicating or branching. */
    public string $newConversationTitle = '';

    public function mount(?string $conversation = null, ?bool $anonymous = null): void
    {
        $this->anonymous = $anonymous ?? request()->routeIs('chats.anonymous') || request()->boolean('anonymous', false);

        if ($conversation !== null) {
            $this->conversationId = $conversation;
        }

        $q = request()->query('q');
        if ($q !== null && $q !== '') {
            $this->question = $q;
            $this->loading = true;
            $this->pendingAsk = true;
        }

        if (! $this->anonymous) {
            $this->loadMessages();
        }

        if ($this->selectedModel === null && $this->availableModels !== []) {
            $this->selectedModel = array_key_first($this->availableModels);
        }

        if ($this->selectedBaseId === null && $this->accessibleBases !== []) {
            $user = auth()->user();
            $provider = app()->bound(AccessibleBasesProvider::class)
                ? app(AccessibleBasesProvider::class)
                : null;
            $this->selectedBaseId = $provider?->getDefaultBaseId($user)
                ?? array_key_first($this->accessibleBases);
        }
    }

    #[Computed]
    public function markState(): array
    {
        $user = auth()->user();
        if (! $user || $this->anonymous) {
            return ['thumbs_up' => [], 'thumbs_down' => [], 'bookmarked' => []];
        }

        $ids = array_filter(array_map(fn ($m) => $m->id ?? null, $this->messages));

        return app(ChatService::class)->getMarkStateForMessages(array_values($ids), $user);
    }

    #[Computed]
    public function accessibleBases(): array
    {
        $user = auth()->user();
        if (! $user || ! app()->bound(AccessibleBasesProvider::class)) {
            return [];
        }

        return app(AccessibleBasesProvider::class)->getAccessibleBases($user);
    }

    #[Computed]
    public function availableModels(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if (! class_exists(AiModel::class)) {
            return [];
        }

        if (! Schema::hasTable('ai_models')) {
            return [];
        }

        $models = AiModel::query()
            ->accessibleBy($user, ChatAgent::class)
            ->with('provider')
            ->orderBy('model_name')
            ->get();

        return $models->pluck('model_name', 'model_name')->all();
    }

    public function removeAttachment(int $index): void
    {
        $arr = $this->attachments;
        if (isset($arr[$index])) {
            $file = $arr[$index];
            if (method_exists($file, 'delete')) {
                $file->delete();
            }
            unset($arr[$index]);
            $this->attachments = array_values($arr);
        }
    }

    public function loadMessages(): void
    {
        if (! $this->conversationId || ! auth()->user()) {
            $this->messages = [];

            return;
        }

        $this->messages = app(ChatService::class)->getMessagesForConversation($this->conversationId, auth()->user());
    }

    public function openForkModal(?string $messageId = null): void
    {
        $this->forkAtMessageId = $messageId;
        $this->newConversationTitle = '';
        $this->showForkModal = true;
    }

    public function forkConversation(): void
    {
        $user = auth()->user();
        if (! $user || ! $this->conversationId) {
            abort(403);
        }

        $clonePermissions = $this->forkPermissionChoice === 'clone';
        $customTitle = trim($this->newConversationTitle) !== '' ? $this->newConversationTitle : null;
        $chatService = app(ChatService::class);

        if ($this->forkAtMessageId) {
            $newConversationId = $chatService->forkConversationAtMessage(
                $this->conversationId,
                $this->forkAtMessageId,
                $user,
                $clonePermissions,
                $customTitle
            );
        } else {
            $newConversationId = $chatService->cloneConversation(
                $this->conversationId,
                $user,
                $clonePermissions,
                $customTitle
            );
        }

        $this->showForkModal = false;
        $this->forkAtMessageId = null;
        $this->newConversationTitle = '';
        $this->redirect(route('chats.show', ['conversation' => $newConversationId]), navigate: true);
    }

    public function downloadTranscript(): StreamedResponse
    {
        $user = auth()->user();
        if (! $user || $this->anonymous || ! $this->conversationId) {
            abort(403);
        }

        app(ChatService::class)->validateConversationOwnership($this->conversationId, $user);

        $title = app(ChatService::class)->getConversationTitle($this->conversationId, $user)
            ?? 'chat-'.$this->conversationId;
        $filename = Str::slug($title).'-'.now()->format('Y-m-d-His').'.md';

        $lines = ["# {$title}\n"];
        foreach ($this->messages as $m) {
            $name = $m->name ?? ($m->role === 'user' ? 'User' : 'Assistant');
            $content = $m->content ?? '';
            $lines[] = "## {$name} — {$m->created_at->toDateTimeString()}\n";
            $lines[] = $content."\n";
        }

        $content = implode("\n", $lines);

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/markdown; charset=utf-8',
        ]);
    }

    public function toggleThumbsUp(string $messageId): void
    {
        $this->toggleReaction($messageId, 'thumbs_up');
    }

    public function toggleThumbsDown(string $messageId): void
    {
        $this->toggleReaction($messageId, 'thumbs_down');
    }

    public function toggleBookmark(string $messageId): void
    {
        if ($this->anonymous) {
            return;
        }

        $user = auth()->user();
        if (! $user || ! $this->conversationId) {
            return;
        }

        app(ChatService::class)->validateConversationOwnership($this->conversationId, $user);

        $message = AgentConversationMessage::findOrFail($messageId);
        if ($message->conversation_id !== $this->conversationId) {
            return;
        }

        Bookmark::toggle($message, $user);
        $this->loadMessages();
    }

    protected function toggleReaction(string $messageId, string $value): void
    {
        if ($this->anonymous) {
            return;
        }

        $user = auth()->user();
        if (! $user || ! $this->conversationId) {
            return;
        }

        app(ChatService::class)->validateConversationOwnership($this->conversationId, $user);

        $message = AgentConversationMessage::findOrFail($messageId);
        if ($message->conversation_id !== $this->conversationId) {
            return;
        }

        $other = $value === 'thumbs_up' ? 'thumbs_down' : 'thumbs_up';
        Reaction::remove($message, $user, $other);
        Reaction::toggle($message, $user, $value);
        $this->loadMessages();
    }

    public function submitPrompt(): void
    {
        $rules = [
            'prompt' => 'required|string|max:10000',
        ];
        if (! $this->anonymous && $this->attachments !== []) {
            $rules['attachments.*'] = 'file|mimes:pdf|max:10240';
        }
        $this->validate($rules);

        $this->question = $this->prompt;
        $this->prompt = '';
        $this->answer = '';
        $this->loading = true;

        if ($this->anonymous) {
            $this->js('$wire.ask()');

            return;
        }

        // Process file uploads before ask
        if ($this->attachments !== [] && $this->selectedBaseId && app()->bound(ChatUploadService::class)) {
            try {
                $uploadService = app(ChatUploadService::class);
                $conversationId = $this->conversationId;
                if (! $conversationId) {
                    $conversationId = app(ConversationStore::class)->storeConversation(
                        auth()->id(),
                        Str::limit($this->question, 50)
                    );
                    $this->conversationId = $conversationId;
                }
                $this->pendingAttachments = $uploadService->processUploads(
                    auth()->user(),
                    $conversationId,
                    $this->attachments,
                    $this->selectedBaseId
                );
                $this->attachments = [];
            } catch (\Throwable $e) {
                $this->loading = false;
                throw $e;
            }
        }

        if (! $this->conversationId) {
            $conversationId = app(ConversationStore::class)->storeConversation(
                auth()->id(),
                Str::limit($this->question, 50)
            );
            $this->conversationId = $conversationId;
            $url = route('chats.show', ['conversation' => $conversationId]);
            $this->js('history.replaceState({}, "", '.Js::from($url).'); $wire.ask();');

            return;
        }

        $this->js('$wire.ask()');
    }

    public function ask(ChatService $chatService): void
    {
        if (empty($this->question)) {
            $this->loading = false;

            return;
        }

        $executed = RateLimiter::attempt('chat', 30, function () use ($chatService) {
            $this->doAsk($chatService);
        }, 60);

        if (! $executed) {
            $this->loading = false;

            throw ValidationException::withMessages([
                'prompt' => __('Too many requests. Please try again in a minute.'),
            ]);
        }
    }

    protected function doAsk(ChatService $chatService): void
    {
        try {
            Log::info('ChatsShow::doAsk start', [
                'conversation_id' => $this->conversationId,
                'anonymous' => $this->anonymous,
                'question_length' => strlen($this->question ?? ''),
            ]);

            if ($this->anonymous) {
                $deltaCount = 0;
                $fullAnswer = '';
                foreach ($chatService->streamAnonymousResponse(
                    $this->question,
                    $this->selectedModel !== '' ? $this->selectedModel : null,
                    auth()->user(),
                    $this->messages
                ) as $delta) {
                    $deltaCount++;
                    $fullAnswer .= $delta;
                    $this->stream(to: 'answer', content: $delta, replace: $deltaCount === 1);
                }
                $this->anonymousAnswer = $fullAnswer;
                Log::info('ChatsShow::doAsk complete (anonymous)', ['delta_count' => $deltaCount ?? 0]);
            } else {
                $onComplete = function (?string $newConversationId) {
                    if ($newConversationId && $this->conversationId !== $newConversationId) {
                        $this->conversationId = $newConversationId;
                        $this->dispatch('url-update', url: route('chats.show', ['conversation' => $newConversationId]));
                    }
                    $conversationId = $newConversationId ?? $this->conversationId;
                    if ($this->pendingAttachments !== [] && $conversationId && app()->bound(ChatUploadService::class)) {
                        $lastUserMessage = AgentConversationMessage::where('conversation_id', $conversationId)
                            ->where('role', 'user')
                            ->orderByDesc('created_at')
                            ->first();
                        if ($lastUserMessage) {
                            app(ChatUploadService::class)->createMessageAttachments(
                                $lastUserMessage->id,
                                $this->pendingAttachments
                            );
                        }
                        $this->pendingAttachments = [];
                    }
                    $this->loadMessages();
                };

                $deltaCount = 0;
                foreach ($chatService->streamResponse(
                    $this->question,
                    auth()->user(),
                    $this->conversationId,
                    $onComplete,
                    $this->selectedModel !== '' ? $this->selectedModel : null
                ) as $delta) {
                    $deltaCount++;
                    $this->stream(to: 'answer', content: $delta, replace: $deltaCount === 1);
                }

                Log::info('ChatsShow::doAsk complete', [
                    'conversation_id' => $this->conversationId,
                    'delta_count' => $deltaCount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ChatsShow::doAsk failed', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            if ($this->anonymous && $this->question !== null && $this->question !== '') {
                $userName = auth()->user()?->name ?? auth()->user()?->email ?? 'You';
                $assistantName = ($this->availableModels[$this->selectedModel] ?? null) ?: 'Assistant';
                $this->messages[] = (object) [
                    'id' => null,
                    'role' => 'user',
                    'content' => $this->question,
                    'name' => $userName,
                    'created_at' => now(),
                ];
                $this->messages[] = (object) [
                    'id' => null,
                    'role' => 'assistant',
                    'content' => $this->anonymousAnswer,
                    'name' => $assistantName,
                    'created_at' => now(),
                ];
            }
            $this->question = null;
            $this->answer = '';
            $this->anonymousAnswer = '';
            $this->loading = false;
        }
    }

    public function render()
    {
        $user = auth()->user();
        $conversationTitle = $this->anonymous
            ? __('Anonymous chat')
            : ($this->conversationId && $user
                ? app(ChatService::class)->getConversationTitle($this->conversationId, $user)
                : null);

        return view('fluxui-chat::livewire.chats-show', [
            'conversationTitle' => $conversationTitle,
            'markState' => $this->markState,
            'anonymous' => $this->anonymous,
        ])->layout(config('fluxui-chat.layout', 'components.layouts.app.sidebar'));
    }
}
