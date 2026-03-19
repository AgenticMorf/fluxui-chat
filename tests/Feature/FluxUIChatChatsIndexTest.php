<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsIndex;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Support\Str;
use Livewire\Livewire;

test('chats index requires authentication', function () {
    $this->get(route('chats.index'))
        ->assertRedirect();
});

test('chats index renders for authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ChatsIndex::class)
        ->assertOk();
});

test('chats index shows user conversations', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, ['msg_'.Str::ulid()]);

    $this->actingAs($user);

    $component = Livewire::test(ChatsIndex::class);

    expect($component->get('conversations')->total())->toBe(1)
        ->and($component->get('conversations')->items()[0]->id)->toBe($conversationId);
});

test('chats index filter search resets page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsIndex::class)
        ->set('filterSearch', 'test');

    expect($component->get('filterSearch'))->toBe('test');
});

test('chats index shows empty when user has no conversations', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsIndex::class);

    expect($component->get('conversations')->total())->toBe(0);
});
