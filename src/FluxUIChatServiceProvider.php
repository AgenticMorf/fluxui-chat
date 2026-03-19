<?php

namespace AgenticMorf\FluxUIChat;

use AgenticMorf\FluxUIChat\Contracts\AccessibleBasesProvider;
use AgenticMorf\FluxUIChat\Contracts\ChatUploadService;
use AgenticMorf\FluxUIChat\Services\ChatService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class FluxUIChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fluxui-chat.php', 'fluxui-chat');

        $this->app->bind(ChatService::class, fn () => new ChatService);

        if ($provider = config('fluxui-chat.accessible_bases_provider')) {
            $this->app->bind(AccessibleBasesProvider::class, $provider);
        }

        if ($service = config('fluxui-chat.chat_upload_service')) {
            $this->app->bind(ChatUploadService::class, $service);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fluxui-chat');

        Blade::component('fluxui-chat::components.chats.layout', 'chats.layout');
        Blade::component('fluxui-chat::components.chats.table', 'chats.table');

        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fluxui-chat.php' => config_path('fluxui-chat.php'),
            ], 'fluxui-chat-config');
        }
    }
}
