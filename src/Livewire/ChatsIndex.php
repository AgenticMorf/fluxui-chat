<?php

namespace AgenticMorf\FluxUIChat\Livewire;

use AgenticMorf\FluxUIChat\Services\ChatService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app.sidebar')]
#[Title('Chats')]
class ChatsIndex extends Component
{
    use WithPagination;

    public string $filterSearch = '';

    public string $filterDateRange = '';

    public string $filterModel = '';

    public string $sort = 'updated_at_desc';

    public function mount(): void
    {
        //
    }

    #[Computed]
    public function conversations()
    {
        $user = auth()->user();
        if (! $user) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $filters = [
            'search' => $this->filterSearch,
            'date_range' => $this->filterDateRange ?: null,
            'model' => $this->filterModel ?: null,
            'sort' => $this->sort,
        ];

        return app(ChatService::class)->getAccessibleConversations($user, $filters, 15);
    }

    #[Computed]
    public function distinctModels(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return app(ChatService::class)->getDistinctModelsForUser($user);
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateRange(): void
    {
        $this->resetPage();
    }

    public function updatedFilterModel(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('fluxui-chat::livewire.chats-index', [
            'conversations' => $this->conversations,
            'distinctModels' => $this->distinctModels,
        ])->layout(config('fluxui-chat.layout', 'components.layouts.app.sidebar'));
    }
}
