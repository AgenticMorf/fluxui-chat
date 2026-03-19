<x-chats.layout
    :heading="$this->conversationTitle ?? __('Edit conversation')"
    :subheading="__('Message timeline')"
    :conversation="$conversationId"
    :wide="true"
>
    <x-slot name="headingActions">
        @if (count($this->messages) > 0)
            <flux:button
                size="sm"
                variant="ghost"
                icon="document-duplicate"
                wire:click="openForkModal"
                aria-label="{{ __('Duplicate conversation') }}"
            >
                {{ __('Duplicate') }}
            </flux:button>
            <flux:modal wire:model.self="showForkModal" name="fork-conversation-edit" class="max-w-lg" focusable>
                <form wire:submit="forkConversation" class="space-y-6">
                    <flux:heading size="lg">{{ __('Duplicate conversation') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Create a copy of this conversation and all its messages. Choose who will have access to the new conversation.') }}
                    </flux:subheading>
                    <flux:input
                        wire:model="newConversationTitle"
                        :label="__('Conversation title (optional)')"
                        :placeholder="__('Leave blank to use default')"
                    />
                    <flux:field>
                        <flux:radio.group wire:model="forkPermissionChoice">
                            <flux:radio
                                value="clone"
                                :label="__('Clone all permissions')"
                                :description="__('Copy access for everyone who can see this conversation')"
                            />
                            <flux:radio
                                value="private"
                                :label="__('Private')"
                                :description="__('Only I will have access to the new conversation')"
                            />
                        </flux:radio.group>
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">{{ __('Duplicate') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    </x-slot>
    @if (count($this->messages) === 0)
        <flux:text variant="subtle">{{ __('No messages in this conversation.') }}</flux:text>
    @else
        <x-chats.table>
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Author') }}</flux:table.column>
                <flux:table.column>{{ __('Content preview') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->messages as $message)
                    <flux:table.row :key="$loop->index">
                        <flux:table.cell class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $message->created_at->format('M j, g:i A') }}
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
    @endif
</x-chats.layout>
