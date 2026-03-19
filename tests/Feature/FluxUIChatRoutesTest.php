<?php

use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Support\Str;

test('chat redirects to chats index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('chat'))
        ->assertRedirect(route('chats.index'));
});

test('chats index route resolves', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('chats.index'))
        ->assertOk();
});

test('chats new route resolves', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('chats.new'))
        ->assertOk();
});

test('chats show route resolves for owned conversation', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, ['msg_'.Str::ulid()]);

    $this->actingAs($user);

    $this->get(route('chats.show', ['conversation' => $conversationId]))
        ->assertOk();
});

test('chats edit route resolves for owner', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, ['msg_'.Str::ulid()]);

    $this->actingAs($user);

    $this->get(route('chats.edit', ['conversation' => $conversationId]))
        ->assertOk();
});

test('chats bookmarks route resolves', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('chats.bookmarks'))
        ->assertOk();
});
