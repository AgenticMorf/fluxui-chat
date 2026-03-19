@props([
    'heading' => '',
    'subheading' => '',
    'conversation' => null,
    'wide' => true,
    'fillViewport' => false,
])

<div class="flex max-md:flex-col {{ $fillViewport ? 'h-[calc(100vh-var(--header-height,4rem))] min-h-0' : 'items-start' }}">
    <div class="me-10 w-full shrink-0 pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item :href="route('chats.index')" :current="request()->routeIs('chats.index')" wire:navigate>
                {{ __('All conversations') }}
            </flux:navlist.item>
            <flux:navlist.item :href="route('chats.bookmarks')" :current="request()->routeIs('chats.bookmarks')" wire:navigate>
                {{ __('Bookmarks') }}
            </flux:navlist.item>
            @if ($conversation)
                <flux:navlist.item :href="route('chats.show', $conversation)" :current="request()->routeIs('chats.show')" wire:navigate>
                    {{ __('Chat') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('chats.edit', $conversation)" :current="request()->routeIs('chats.edit')" wire:navigate>
                    {{ __('Edit') }}
                </flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex min-w-0 flex-1 flex-col {{ $fillViewport ? 'min-h-0 overflow-hidden' : 'self-stretch max-md:pt-6' }}">
        <div class="flex shrink-0 items-start justify-between gap-4">
            <div class="min-w-0">
                <flux:heading class="{{ $fillViewport ? 'shrink-0' : '' }}">{{ $heading }}</flux:heading>
                <flux:subheading class="{{ $fillViewport ? 'shrink-0' : '' }}">{{ $subheading }}</flux:subheading>
            </div>
            @isset($headingActions)
                <div class="shrink-0">{{ $headingActions }}</div>
            @endisset
        </div>

        <div class="mt-5 w-full {{ $wide ? '' : 'max-w-lg' }} {{ $fillViewport ? 'flex min-h-0 flex-1 flex-col overflow-hidden' : '' }}">
            {{ $slot }}
        </div>
    </div>
</div>
