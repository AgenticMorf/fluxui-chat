<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsEdit;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('chats edit requires authentication', function () {
    $conversationId = 'con_'.Str::ulid();

    $this->get(route('chats.edit', ['conversation' => $conversationId]))
        ->assertRedirect();
});

test('chats edit renders for owner', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, ['msg_'.Str::ulid()]);

    $this->actingAs($user);

    Livewire::test(ChatsEdit::class, ['conversation' => $conversationId])
        ->assertOk()
        ->assertSee('Message timeline');
});

test('chats edit loads messages', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $msg2 = 'msg_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, [$msg1, $msg2]);

    $this->actingAs($user);

    $component = Livewire::test(ChatsEdit::class, ['conversation' => $conversationId]);

    $messages = $component->get('messages');
    expect($messages)->toHaveCount(2);
});

test('chats edit shows conversation title', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'My Edit Chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ChatsEdit::class, ['conversation' => $conversationId]);

    expect($component->get('conversationTitle'))->toBe('My Edit Chat');
});
