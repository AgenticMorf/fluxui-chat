<section class="w-full">
    <x-chats.layout heading="{{ __('All conversations') }}" subheading="{{ __('Browse and manage your chat history') }}" :wide="true">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-end gap-4">
                <flux:input
                    wire:model.live.debounce.300ms="filterSearch"
                    label="{{ __('Search by title') }}"
                    placeholder="{{ __('Search...') }}"
                    class="w-64"
                />
                <flux:select wire:model.live="filterDateRange" label="{{ __('Date range') }}" class="w-36">
                    <option value="">{{ __('All time') }}</option>
                    <option value="7d">{{ __('Last 7 days') }}</option>
                    <option value="30d">{{ __('Last 30 days') }}</option>
                    <option value="90d">{{ __('Last 90 days') }}</option>
                </flux:select>
                @if (count($distinctModels) > 0)
                    <flux:select wire:model.live="filterModel" label="{{ __('Model') }}" class="w-40">
                        <option value="">{{ __('All models') }}</option>
                        @foreach ($distinctModels as $model)
                            <option value="{{ $model }}">{{ $model }}</option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:select wire:model.live="sort" label="{{ __('Sort') }}" class="w-40">
                    <option value="updated_at_desc">{{ __('Last activity') }}</option>
                    <option value="created_at_desc">{{ __('Newest first') }}</option>
                    <option value="title_asc">{{ __('Title A–Z') }}</option>
                    <option value="title_desc">{{ __('Title Z–A') }}</option>
                </flux:select>
            </div>
            <flux:button :href="route('chats.anonymous')" variant="ghost" icon="message-square-dashed" wire:navigate :aria-label="__('Anonymous Chat')">
                {{ __('Anonymous Chat') }}
            </flux:button>
            <flux:button :href="route('chats.new')" variant="primary" icon="plus" wire:navigate>
                {{ __('New chat') }}
            </flux:button>
        </div>

        @if ($conversations->isEmpty())
            <flux:text variant="subtle" class="mt-4">{{ __('No conversations yet.') }}</flux:text>
            <flux:button :href="route('chats.new')" variant="primary" class="mt-4" wire:navigate>
                {{ __('Start a conversation') }}
            </flux:button>
        @else
            <x-chats.table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Last activity') }}</flux:table.column>
                    <flux:table.column>{{ __('Messages') }}</flux:table.column>
                    <flux:table.column class="text-end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($conversations as $conv)
                        <flux:table.row :key="$conv->id">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('chats.show', $conv->id)" wire:navigate>
                                    {{ Str::limit($conv->title, 50) }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ \Carbon\Carbon::parse($conv->updated_at)->format('M j, g:i A') }}</flux:table.cell>
                            <flux:table.cell>{{ $conv->message_count }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                <flux:button :href="route('chats.show', $conv->id)" variant="ghost" size="sm" wire:navigate>
                                    {{ __('View') }}
                                </flux:button>
                                <flux:button :href="route('chats.edit', $conv->id)" variant="ghost" size="sm" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </x-chats.table>

            <div class="mt-4">
                {{ $conversations->links() }}
            </div>
        @endif
    </x-chats.layout>
</section>
