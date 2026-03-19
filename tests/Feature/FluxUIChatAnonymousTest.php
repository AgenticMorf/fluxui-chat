<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsShow;
use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maize\Markable\Models\Bookmark;
use Maize\Markable\Models\Reaction;

test('anonymous chat requires authentication', function () {
    $this->get(route('chats.anonymous'))
        ->assertRedirect();
});

test('anonymous chat renders for authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ChatsShow::class, ['anonymous' => true])
        ->assertOk()
        ->assertSee(__('Anonymous chat'))
        ->assertSee(__('This is an anonymous chat. Navigating away from this screen will delete chat.'));
});

test('anonymous chat does not show duplicate or download buttons', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsShow::class, ['anonymous' => true]);

    $html = $component->html();
    expect($html)->not->toContain(__('Duplicate'));
    expect($html)->not->toContain(__('Download transcript'));
});

test('anonymous chat does not show fork or mark buttons on messages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsShow::class, ['anonymous' => true])
        ->set('messages', [
            (object) [
                'id' => null,
                'role' => 'assistant',
                'content' => 'Test',
                'created_at' => now(),
                'name' => 'Assistant',
            ],
        ]);

    $html = $component->html();
    expect($html)->not->toContain('toggleThumbsUp')
        ->not->toContain('toggleThumbsDown')
        ->not->toContain('toggleBookmark')
        ->not->toContain('openForkModal');
});

test('toggle thumbs up is no op when anonymous', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();
    $this->createConversationAndMessage($user->id, $conversationId, $messageId);

    $this->actingAs($user);

    Livewire::test(ChatsShow::class, [
        'conversation' => $conversationId,
        'anonymous' => true,
    ])->call('toggleThumbsUp', $messageId);

    expect(Reaction::query()
        ->where('markable_type', AgentConversationMessage::class)
        ->where('markable_id', $messageId)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

test('toggle thumbs down is no op when anonymous', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();
    $this->createConversationAndMessage($user->id, $conversationId, $messageId);

    $this->actingAs($user);

    Livewire::test(ChatsShow::class, [
        'conversation' => $conversationId,
        'anonymous' => true,
    ])->call('toggleThumbsDown', $messageId);

    expect(Reaction::query()
        ->where('markable_type', AgentConversationMessage::class)
        ->where('markable_id', $messageId)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

test('toggle bookmark is no op when anonymous', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();
    $this->createConversationAndMessage($user->id, $conversationId, $messageId);

    $this->actingAs($user);

    Livewire::test(ChatsShow::class, [
        'conversation' => $conversationId,
        'anonymous' => true,
    ])->call('toggleBookmark', $messageId);

    expect(Bookmark::query()
        ->where('markable_type', AgentConversationMessage::class)
        ->where('markable_id', $messageId)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

test('download transcript aborts when anonymous', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test(ChatsShow::class, ['anonymous' => true])
        ->call('downloadTranscript')
        ->assertStatus(403);
});

test('fork conversation aborts when anonymous', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test(ChatsShow::class, ['anonymous' => true])
        ->call('forkConversation')
        ->assertStatus(403);
});

test('anonymous chat has no conversation id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsShow::class, ['anonymous' => true]);

    expect($component->get('conversationId'))->toBeNull();
});

test('anonymous chat does not persist to database', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $countBefore = DB::table('agent_conversations')->count();

    Livewire::test(ChatsShow::class, ['anonymous' => true])
        ->set('question', 'Hello')
        ->set('answer', 'Hi there')
        ->call('submitPrompt');

    expect(DB::table('agent_conversations')->count())->toBe($countBefore);
});
