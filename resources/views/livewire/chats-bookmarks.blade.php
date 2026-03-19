<x-chats.layout
    heading="{{ __('Bookmarks') }}"
    subheading="{{ __('Saved messages from your conversations') }}"
    :wide="true"
>
    @if ($this->bookmarks->isEmpty())
        <flux:text variant="subtle">{{ __('No bookmarked messages yet.') }}</flux:text>
        <flux:text variant="subtle" class="mt-1">{{ __('Bookmark messages from any chat to find them here.') }}</flux:text>
    @else
        <x-chats.table>
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Conversation') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Author') }}</flux:table.column>
                <flux:table.column>{{ __('Content preview') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->bookmarks as $message)
                    <flux:table.row :key="$message->id">
                        <flux:table.cell class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $message->created_at->format('M j, g:i A') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('chats.show', $message->conversation_id)" wire:navigate>
                                {{ Str::limit($message->conversation_title ?? __('Untitled'), 30) }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge>{{ $message->role }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:avatar :name="$message->name" size="xs" :icon="$message->role === 'assistant' ? 'sparkles' : null" />
                                <span>{{ $message->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <details class="group">
                                <summary class="cursor-pointer list-none max-w-md truncate text-sm [&::-webkit-details-marker]:hidden before:me-1 before:inline-block before:content-['▸'] group-open:before:content-['▾']">
                                    {{ Str::limit(strip_tags(Str::markdown($message->content)), 80) }}
                                </summary>
                                <div class="mt-2 max-w-2xl rounded-md border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 [&_p]:my-1">
                                    {!! Str::markdown($message->content) !!}
                                </div>
                            </details>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </x-chats.table>

        <div class="mt-4">
            {{ $this->bookmarks->links() }}
        </div>
    @endif
</x-chats.layout>
