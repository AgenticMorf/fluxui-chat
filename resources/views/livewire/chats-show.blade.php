<x-chats.layout
    :heading="$conversationTitle ?? __('New chat')"
    :subheading="$conversationId ? __('Chat') : __('Start a new conversation')"
    :conversation="$conversationId"
    :wide="true"
    :fill-viewport="true"
>
    <x-slot name="headingActions">
        @if(! $anonymous && $conversationId && count($messages) > 0)
            <flux:button
                size="sm"
                variant="ghost"
                icon="document-duplicate"
                wire:click="openForkModal"
                aria-label="{{ __('Duplicate conversation') }}"
            >
                {{ __('Duplicate') }}
            </flux:button>
            <flux:button
                size="sm"
                variant="ghost"
                icon="arrow-down-tray"
                wire:click="downloadTranscript"
                aria-label="{{ __('Download transcript') }}"
            >
                {{ __('Download transcript') }}
            </flux:button>
            <flux:modal wire:model.self="showForkModal" name="fork-conversation" class="max-w-lg" focusable>
                <form wire:submit="forkConversation" class="space-y-6">
                    <flux:heading size="lg">
                        {{ $forkAtMessageId ? __('Start new chat from here') : __('Duplicate conversation') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ $forkAtMessageId
                            ? __('Create a new conversation with messages up to this point. Choose who will have access.')
                            : __('Create a copy of this conversation and all its messages. Choose who will have access to the new conversation.') }}
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
                        <flux:button type="submit" variant="primary">
                            {{ $forkAtMessageId ? __('Start new chat') : __('Duplicate') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    </x-slot>
<div class="mx-auto flex w-full max-w-full flex-1 flex-col min-h-0 lg:w-1/2" x-data>
    @if($anonymous)
        <flux:callout
            variant="warning"
            icon="exclamation-circle"
            :heading="__('This is an anonymous chat. Navigating away from this screen will delete chat.')"
            class="shrink-0 mx-4 mt-4 mb-0"
        />
    @endif
    @script
    <script>
        if ($wire.pendingAsk) {
            $wire.$set('pendingAsk', false);
            history.replaceState({}, '', location.pathname);
            $wire.ask();
        }
        $wire.$on('url-update', (event) => {
            const url = event?.url ?? event?.detail?.url;
            if (url) history.replaceState({}, '', url);
        });
    </script>
    @endscript
    <div
        id="chat-region"
        role="log"
        aria-labelledby="chat-heading"
        aria-live="polite"
        class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 space-y-4"
        x-data="{
            userAtBottom: true,
            init() {
                const el = $el;
                const threshold = 80;
                const atBottom = () => el.scrollHeight - el.scrollTop - el.clientHeight < threshold;
                el.addEventListener('scroll', () => { this.userAtBottom = atBottom(); });
                const observer = new MutationObserver(() => {
                    if (this.userAtBottom) el.scrollTop = el.scrollHeight;
                });
                observer.observe(el, { childList: true, subtree: true, characterData: true });
                el.scrollTop = el.scrollHeight;
                if (location.hash) {
                    const target = document.querySelector(location.hash);
                    if (target) requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
                }
            }
        }"
    >
        <h4 id="chat-heading" class="sr-only">{{ __('Conversation') }}</h4>
        @foreach($messages as $message)
            <div id="msg-{{ $message->id }}" class="flex w-full scroll-mt-4 {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <flux:card size="sm" class="w-[80%] min-w-0">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <flux:avatar :name="$message->name" size="xs" :icon="$message->role === 'assistant' ? 'sparkles' : null" :class="$message->role === 'user' ? 'ring-2 ring-inset ring-accent' : ''" />
                            <flux:subheading size="xs" class="text-zinc-600 dark:text-zinc-400">{{ $message->name }}</flux:subheading>
                            <a
                                href="#msg-{{ $message->id }}"
                                @click.prevent="location.hash = 'msg-{{ $message->id }}'; document.getElementById('msg-{{ $message->id }}')?.scrollIntoView({ behavior: 'smooth' })"
                                class="ml-auto text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-400 underline decoration-dashed underline-offset-2 cursor-pointer text-xs"
                            >
                                {{ $message->created_at->format('M j, g:i A') }}
                            </a>
                        </div>
                        <flux:text>{!! Str::markdown($message->content) !!}</flux:text>
                        @if(! $anonymous && ! empty($message->id ?? null))
                            <div class="flex items-center gap-1 pt-2 mt-1 border-t border-zinc-200/50 dark:border-zinc-700/50 {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                                @if($message->role === 'assistant')
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="thumbs-up"
                                        :class="in_array($message->id, $markState['thumbs_up'] ?? []) ? 'text-accent' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                        wire:click='toggleThumbsUp({{ json_encode($message->id) }})'
                                        aria-label="{{ __('Thumbs up') }}"
                                    />
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="thumbs-down"
                                        :class="in_array($message->id, $markState['thumbs_down'] ?? []) ? 'text-accent' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                        wire:click='toggleThumbsDown({{ json_encode($message->id) }})'
                                        aria-label="{{ __('Thumbs down') }}"
                                    />
                                @endif
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="bookmark"
                                    :class="in_array($message->id, $markState['bookmarked'] ?? []) ? 'text-accent [&_[data-slot=icon]]:fill-current' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                    wire:click='toggleBookmark({{ json_encode($message->id) }})'
                                    aria-label="{{ __('Bookmark') }}"
                                />
                                <flux:tooltip :content="__('Start new chat from here')" class="contents">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="git-branch"
                                        wire:click='openForkModal({{ json_encode($message->id) }})'
                                        class="text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                                        aria-label="{{ __('Start new chat from here') }}"
                                    />
                                </flux:tooltip>
                                <flux:tooltip :content="__('Copy to clipboard')" class="contents">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        square
                                        class="group text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                                        x-data="{ content: @js(strip_tags(Str::markdown($message->content ?? ''))) }"
                                        x-on:click="navigator.clipboard?.writeText(content); $el.setAttribute('data-copied', ''); setTimeout(() => $el.removeAttribute('data-copied'), 2000)"
                                        aria-label="{{ __('Copy to clipboard') }}"
                                    >
                                        <flux:icon.clipboard-document-check variant="mini" class="hidden group-data-[copied]:block" />
                                        <flux:icon.clipboard-document variant="mini" class="block group-data-[copied]:hidden" />
                                    </flux:button>
                                </flux:tooltip>
                            </div>
                        @endif
                    </div>
                </flux:card>
            </div>
        @endforeach

        @if($question)
            <div class="flex w-full justify-end">
                <flux:card size="sm" class="w-[80%] min-w-0">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <flux:avatar :name="auth()->user()?->name ?? 'You'" size="xs" class="ring-2 ring-inset ring-accent" />
                            <flux:subheading size="xs" class="text-zinc-600 dark:text-zinc-400">{{ auth()->user()?->name ?? 'You' }}</flux:subheading>
                            <flux:subheading size="xs" class="ml-auto text-zinc-400 dark:text-zinc-500">{{ now()->format('M j, g:i A') }}</flux:subheading>
                        </div>
                        <flux:text>{{ $question }}</flux:text>
                    </div>
                </flux:card>
            </div>

            <div class="flex w-full justify-start">
                <flux:card size="sm" class="w-[80%] min-w-0">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <flux:avatar :name="$this->availableModels[$this->selectedModel] ?? 'Assistant'" size="xs" icon="sparkles" />
                            <flux:subheading size="xs" class="text-zinc-600 dark:text-zinc-400">{{ $this->availableModels[$this->selectedModel] ?? 'Assistant' }}</flux:subheading>
                            <flux:subheading size="xs" class="ml-auto text-zinc-400 dark:text-zinc-500">{{ now()->format('M j, g:i A') }}</flux:subheading>
                        </div>
                        <div wire:stream="answer">
                            @if($answer !== '')
                                <flux:text>{!! Str::markdown($answer) !!}</flux:text>
                            @else
                                <flux:skeleton.group animate="shimmer">
                                    <flux:skeleton.line />
                                    <flux:skeleton.line class="w-2/3" />
                                    <flux:skeleton.line class="w-1/2" />
                                </flux:skeleton.group>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </div>
        @endif
    </div>

    <div class="shrink-0 p-4">
        <form wire:submit="submitPrompt">
            <flux:composer
                wire:model="prompt"
                label="{{ __('Message') }}"
                label:sr-only
                placeholder="{{ __('Ask about your documents...') }}"
                rows="2"
                submit="cmd+enter"
                wire:loading.attr="disabled"
                wire:target="submitPrompt, ask"
            >
                @if(! $anonymous && count($this->accessibleBases) > 0)
                    <x-slot name="header">
                        <flux:file-upload wire:model="attachments" multiple label="{{ __('Attach files') }}">
                            <flux:file-upload.dropzone
                                heading="{{ __('Drop files here or click to browse') }}"
                                text="{{ __('PDF up to 10MB') }}"
                                with-progress
                                inline
                            />
                        </flux:file-upload>
                        @if(count($attachments) > 0)
                            <div class="mt-3 flex flex-col gap-2">
                                @foreach($attachments as $index => $file)
                                    <flux:file-item
                                        :heading="$file->getClientOriginalName()"
                                        :size="$file->getSize()"
                                    >
                                        <x-slot name="actions">
                                            <flux:file-item.remove wire:click="removeAttachment({{ $index }})" aria-label="{{ __('Remove') }} {{ $file->getClientOriginalName() }}" />
                                        </x-slot>
                                    </flux:file-item>
                                @endforeach
                            </div>
                        @endif
                    </x-slot>
                @endif
                <x-slot name="actionsTrailing">
                    @if(! $anonymous && count($this->accessibleBases) > 0)
                        <flux:select
                            wire:model="selectedBaseId"
                            size="sm"
                            class="min-w-[140px]"
                            aria-label="{{ __('Base') }}"
                        >
                            @foreach($this->accessibleBases as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if($this->availableModels !== [])
                        <flux:select
                            wire:model="selectedModel"
                            size="sm"
                            class="min-w-[140px]"
                            aria-label="{{ __('Model') }}"
                        >
                            @foreach($this->availableModels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane" :disabled="$loading" aria-label="{{ __('Send message') }}" />
                </x-slot>
            </flux:composer>
        </form>
    </div>
</div>
</x-chats.layout>
