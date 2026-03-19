<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsShow;
use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use AgenticMorf\FluxUIChat\Services\ChatService;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maize\Markable\Models\Bookmark;
use Maize\Markable\Models\Reaction;

test('user can thumbs up thumbs down and bookmark message', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();

    $this->createConversationAndMessage($user->id, $conversationId, $messageId);

    $this->actingAs($user);

    Livewire::test(ChatsShow::class, ['conversation' => $conversationId])
        ->call('toggleThumbsUp', $messageId);

    expect(Reaction::has(
        AgentConversationMessage::find($messageId),
        $user,
        'thumbs_up'
    ))->toBeTrue();

    Livewire::test(ChatsShow::class, ['conversation' => $conversationId])
        ->call('toggleThumbsUp', $messageId);

    expect(Reaction::has(
        AgentConversationMessage::find($messageId),
        $user,
        'thumbs_up'
    ))->toBeFalse();

    Livewire::test(ChatsShow::class, ['conversation' => $conversationId])
        ->call('toggleThumbsDown', $messageId);

    expect(Reaction::has(
        AgentConversationMessage::find($messageId),
        $user,
        'thumbs_down'
    ))->toBeTrue();

    Livewire::test(ChatsShow::class, ['conversation' => $conversationId])
        ->call('toggleBookmark', $messageId);

    expect(Bookmark::query()
        ->where('markable_type', AgentConversationMessage::class)
        ->where('markable_id', $messageId)
        ->where('user_id', $user->id)
        ->exists())->toBeTrue();

    Livewire::test(ChatsShow::class, ['conversation' => $conversationId])
        ->call('toggleBookmark', $messageId);

    expect(Bookmark::query()
        ->where('markable_type', AgentConversationMessage::class)
        ->where('markable_id', $messageId)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

test('unauthorized user cannot mark messages in others conversations', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->setRlsContext($owner->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();

    $this->createConversationAndMessage($owner->id, $conversationId, $messageId);

    $this->actingAs($otherUser);

    $chatService = app(ChatService::class);

    expect(fn () => $chatService->validateConversationOwnership($conversationId, $otherUser))
        ->toThrow(AuthorizationException::class);
});

test('get mark state for messages returns correct state', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $msg2 = 'msg_'.Str::ulid();

    $this->createConversationAndMessage($user->id, $conversationId, $msg1, 'assistant');
    $this->createConversationAndMessage($user->id, $conversationId, $msg2, 'assistant');

    $m1 = AgentConversationMessage::find($msg1);
    $m2 = AgentConversationMessage::find($msg2);

    Reaction::add($m1, $user, 'thumbs_up');
    Reaction::add($m2, $user, 'thumbs_down');
    Bookmark::add($m1, $user);

    $chatService = app(ChatService::class);
    $chatService->validateConversationOwnership($conversationId, $user);

    $state = $chatService->getMarkStateForMessages([$msg1, $msg2], $user);

    expect($state['thumbs_up'])->toContain($msg1)
        ->and($state['thumbs_down'])->toContain($msg2)
        ->and($state['bookmarked'])->toContain($msg1)
        ->and($state['bookmarked'])->not->toContain($msg2);
});
