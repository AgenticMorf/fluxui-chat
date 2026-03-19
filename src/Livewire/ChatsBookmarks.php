<?php

namespace AgenticMorf\FluxUIChat\Livewire;

use AgenticMorf\FluxUIChat\Services\ChatService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app.sidebar')]
#[Title('Bookmarks')]
class ChatsBookmarks extends Component
{
    use WithPagination;

    #[Computed]
    public function bookmarks(): LengthAwarePaginator
    {
        $user = auth()->user();
        if (! $user) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return app(ChatService::class)->getBookmarkedMessages($user, 15);
    }

    public function render()
    {
        return view('fluxui-chat::livewire.chats-bookmarks')
            ->layout(config('fluxui-chat.layout', 'components.layouts.app.sidebar'));
    }
}
