<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsBookmarks;
use AgenticMorf\FluxUIChat\Livewire\ChatsEdit;
use AgenticMorf\FluxUIChat\Livewire\ChatsIndex;
use AgenticMorf\FluxUIChat\Livewire\ChatsShow;
use Illuminate\Support\Facades\Route;

Route::middleware(config('fluxui-chat.middleware', ['web', 'auth']))
    ->group(function (): void {
        Route::get('/chat', fn () => redirect()->route('chats.index'))
            ->name('chat');

        Route::prefix(config('fluxui-chat.route_prefix', 'chats'))
            ->name(config('fluxui-chat.route_name_prefix', 'chats.'))
            ->group(function (): void {
                Route::get('/', ChatsIndex::class)->name('index');
                Route::get('/bookmarks', ChatsBookmarks::class)->name('bookmarks');
                Route::get('/new', ChatsShow::class)->name('new');
                Route::get('/anonymous', ChatsShow::class)->name('anonymous');
                Route::get('/{conversation}/edit', ChatsEdit::class)->name('edit');
                Route::get('/{conversation}', ChatsShow::class)->name('show');
            });
    });
