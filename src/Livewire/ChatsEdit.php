<?php

namespace AgenticMorf\FluxUIChat\Livewire;

use AgenticMorf\FluxUIChat\Services\ChatService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app.sidebar')]
#[Title('Edit Chat')]
class ChatsEdit extends Component
{
    public string $conversationId;

    public bool $showForkModal = false;

    /** @var 'clone'|'private' */
    public string $forkPermissionChoice = 'private';

    /** Optional custom title for the new conversation when duplicating. */
    public string $newConversationTitle = '';

    public function mount(string $conversation): void
    {
        $this->conversationId = $conversation;
    }

    public function getMessagesProperty(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return app(ChatService::class)->getMessagesForConversation($this->conversationId, $user);
    }

    public function getConversationTitleProperty(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return app(ChatService::class)->getConversationTitle($this->conversationId, $user);
    }

    public function openForkModal(): void
    {
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

        $newConversationId = app(ChatService::class)->cloneConversation(
            $this->conversationId,
            $user,
            $clonePermissions,
            $customTitle
        );

        $this->showForkModal = false;
        $this->newConversationTitle = '';
        $this->redirect(route('chats.show', ['conversation' => $newConversationId]), navigate: true);
    }

    public function render()
    {
        return view('fluxui-chat::livewire.chats-edit')
            ->layout(config('fluxui-chat.layout', 'components.layouts.app.sidebar'));
    }
}
