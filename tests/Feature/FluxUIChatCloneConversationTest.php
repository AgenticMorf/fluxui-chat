<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsEdit;
use AgenticMorf\FluxUIChat\Livewire\ChatsShow;
use AgenticMorf\FluxUIChat\Services\ChatService;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('owner can clone conversation with private permissions', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $msg2 = 'msg_'.Str::ulid();

    $this->createConversationWithMessages($user->id, $conversationId, [$msg1, $msg2]);

    $chatService = app(ChatService::class);
    $newId = $chatService->cloneConversation($conversationId, $user, false);

    expect($newId)->not->toBe($conversationId)
        ->not->toBeEmpty();

    $newConv = DB::table('agent_conversations')->where('id', $newId)->first();
    expect($newConv)->not->toBeNull()
        ->and((string) $newConv->user_id)->toBe((string) $user->id)
        ->and($newConv->title)->toContain('(copy)');

    $newMessages = DB::table('agent_conversation_messages')->where('conversation_id', $newId)->get();
    expect($newMessages)->toHaveCount(2);

    $shares = DB::table('conversation_shares')->where('conversation_id', $newId)->get();
    expect($shares)->toHaveCount(0);
});

test('owner can clone conversation with cloned permissions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();

    $this->createConversationWithMessages($user->id, $conversationId, [$msg1]);

    DB::table('conversation_shares')->insert([
        'conversation_id' => $conversationId,
        'shareable_type' => User::class,
        'shareable_id' => $otherUser->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $chatService = app(ChatService::class);
    $newId = $chatService->cloneConversation($conversationId, $user, true);

    $shares = DB::table('conversation_shares')->where('conversation_id', $newId)->get();
    expect($shares)->toHaveCount(1);
    expect($shares[0]->shareable_type)->toBe(User::class)
        ->and((string) $shares[0]->shareable_id)->toBe((string) $otherUser->id);
});

test('cloned messages preserve content and order', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $msg2 = 'msg_'.Str::ulid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Original Chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => $msg1,
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'TestAgent',
            'role' => 'user',
            'content' => 'First message',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ],
        [
            'id' => $msg2,
            'conversation_id' => $conversationId,
            'user_id' => null,
            'agent' => 'TestAgent',
            'role' => 'assistant',
            'content' => 'Second message',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ],
    ]);

    $chatService = app(ChatService::class);
    $newId = $chatService->cloneConversation($conversationId, $user, false);

    $newMessages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $newId)
        ->orderBy('created_at')
        ->get();

    expect($newMessages)->toHaveCount(2);
    expect($newMessages[0]->content)->toBe('First message')
        ->and($newMessages[1]->content)->toBe('Second message')
        ->and($newMessages[0]->role)->toBe('user')
        ->and($newMessages[1]->role)->toBe('assistant');
});

test('livewire fork conversation redirects to new conversation', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();

    $this->createConversationWithMessages($user->id, $conversationId, [$msg1]);

    $countBefore = DB::table('agent_conversations')->count();

    $this->actingAs($user);

    $component = Livewire::test(ChatsShow::class, ['conversation' => $conversationId])
        ->set('forkPermissionChoice', 'private')
        ->call('forkConversation');

    $component->assertRedirect();

    expect(DB::table('agent_conversations')->count())->toBe($countBefore + 1);
});

test('chats edit fork conversation redirects', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();

    $this->createConversationWithMessages($user->id, $conversationId, [$msg1]);

    $this->actingAs($user);

    $component = Livewire::test(ChatsEdit::class, ['conversation' => $conversationId])
        ->set('forkPermissionChoice', 'private')
        ->call('forkConversation');

    $component->assertRedirect();
});

test('fork at message includes only messages up to that point', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $msg2 = 'msg_'.Str::ulid();
    $msg3 = 'msg_'.Str::ulid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'Original',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        ['id' => $msg1, 'conversation_id' => $conversationId, 'user_id' => $user->id, 'agent' => 'Test', 'role' => 'user', 'content' => 'First', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '{}', 'meta' => '{}', 'created_at' => now()->subMinutes(3), 'updated_at' => now()->subMinutes(3)],
        ['id' => $msg2, 'conversation_id' => $conversationId, 'user_id' => null, 'agent' => 'Test', 'role' => 'assistant', 'content' => 'Second', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '{}', 'meta' => '{}', 'created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2)],
        ['id' => $msg3, 'conversation_id' => $conversationId, 'user_id' => $user->id, 'agent' => 'Test', 'role' => 'user', 'content' => 'Third', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '{}', 'meta' => '{}', 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
    ]);

    $chatService = app(ChatService::class);
    $newId = $chatService->forkConversationAtMessage($conversationId, $msg2, $user, false);

    $newMessages = DB::table('agent_conversation_messages')
        ->where('conversation_id', $newId)
        ->orderBy('created_at')
        ->get();

    expect($newMessages)->toHaveCount(2);
    expect($newMessages[0]->content)->toBe('First')
        ->and($newMessages[1]->content)->toBe('Second');
});

test('unauthorized user cannot clone conversation', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->setRlsContext($owner->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();

    $this->createConversationWithMessages($owner->id, $conversationId, [$msg1]);

    $this->actingAs($otherUser);

    $chatService = app(ChatService::class);

    $this->expectException(AuthorizationException::class);

    $chatService->cloneConversation($conversationId, $otherUser, false);
});
