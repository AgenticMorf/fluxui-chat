<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsBookmarks;
use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Support\Str;
use Livewire\Livewire;
use Maize\Markable\Models\Bookmark;

test('chats bookmarks requires authentication', function () {
    $this->get(route('chats.bookmarks'))
        ->assertRedirect();
});

test('chats bookmarks renders for authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ChatsBookmarks::class)
        ->assertOk()
        ->assertSee('Bookmarks');
});

test('chats bookmarks shows empty when no bookmarks', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsBookmarks::class);

    expect($component->get('bookmarks')->total())->toBe(0);
});

test('chats bookmarks shows bookmarked messages', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();
    $this->createConversationAndMessage($user->id, $conversationId, $messageId);

    $message = AgentConversationMessage::find($messageId);
    Bookmark::add($message, $user);

    $this->actingAs($user);

    $component = Livewire::test(ChatsBookmarks::class);

    expect($component->get('bookmarks')->total())->toBe(1)
        ->and($component->get('bookmarks')->items()[0]->id)->toBe($messageId);
});
