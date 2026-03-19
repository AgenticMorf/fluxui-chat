<?php

namespace AgenticMorf\FluxUIChat\Tests;

use AgenticMorf\FluxUIChat\FluxUIChatServiceProvider;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\AiServiceProvider;
use Livewire\LivewireServiceProvider;
use Maize\Markable\MarkableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        if (! class_exists(\App\Models\User::class, false)) {
            class_alias(User::class, \App\Models\User::class);
        }

        parent::setUp();

        config([
            'auth.providers.users.model' => User::class,
            'markable.user_model' => User::class,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            AiServiceProvider::class,
            MarkableServiceProvider::class,
            FluxUIChatServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => redirect('/'))->name('login');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('markable.allowed_values.reaction', ['thumbs_up', 'thumbs_down']);
        $app['config']->set('fluxui-chat.layout', 'fluxui-chat::layouts.minimal');

        $app['view']->addLocation(__DIR__.'/views');
    }

    protected function setRlsContext(string|int|null $userId): void {}

    protected function createConversationAndMessage(
        string $userId,
        string $conversationId,
        string $messageId,
        string $role = 'assistant'
    ): void {
        if (! DB::table('agent_conversations')->where('id', $conversationId)->exists()) {
            DB::table('agent_conversations')->insert([
                'id' => $conversationId,
                'user_id' => $userId,
                'title' => 'Test Chat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('agent_conversation_messages')->insert([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $role === 'user' ? $userId : null,
            'agent' => 'TestAgent',
            'role' => $role,
            'content' => 'Test content',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createConversationWithMessages(string $userId, string $conversationId, array $messageIds): void
    {
        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $userId,
            'title' => 'Test Chat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($messageIds as $i => $messageId) {
            $role = $i === 0 ? 'user' : 'assistant';
            DB::table('agent_conversation_messages')->insert([
                'id' => $messageId,
                'conversation_id' => $conversationId,
                'user_id' => $role === 'user' ? $userId : null,
                'agent' => 'TestAgent',
                'role' => $role,
                'content' => "Message {$i}",
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '{}',
                'meta' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
