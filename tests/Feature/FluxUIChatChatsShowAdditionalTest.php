<?php

use AgenticMorf\FluxUIChat\Livewire\ChatsShow;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use AgenticMorf\FluxUIChat\Services\ChatService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('submit prompt validates required prompt', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test(ChatsShow::class, ['anonymous' => true])
        ->set('prompt', '')
        ->call('submitPrompt')
        ->assertHasErrors(['prompt' => 'required']);
});

test('submit prompt accepts valid prompt', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::actingAs($user)
        ->test(ChatsShow::class, ['anonymous' => true])
        ->set('prompt', 'Hello world')
        ->call('submitPrompt');

    $component->assertHasNoErrors();
    expect($component->get('question'))->toBe('Hello world')
        ->and($component->get('prompt'))->toBe('')
        ->and($component->get('loading'))->toBeTrue();
});

test('download transcript succeeds for owned conversation', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $msg2 = 'msg_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, [$msg1, $msg2]);

    $this->actingAs($user);

    $component = Livewire::actingAs($user)
        ->test(ChatsShow::class, ['conversation' => $conversationId]);

    $component->call('downloadTranscript')
        ->assertOk();
});

test('remove attachment removes at index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ChatsShow::class, ['anonymous' => true])
        ->set('attachments', ['placeholder']);

    $component->call('removeAttachment', 0);

    expect($component->get('attachments'))->toBeEmpty();
});

test('ask respects rate limit', function () {
    RateLimiter::clear('chat');

    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, ['msg_'.Str::ulid()]);

    for ($i = 0; $i < 30; $i++) {
        RateLimiter::hit('chat', 60);
    }

    $this->actingAs($user);

    Livewire::actingAs($user)
        ->test(ChatsShow::class, ['conversation' => $conversationId])
        ->set('question', 'Hello')
        ->call('ask', app(ChatService::class))
        ->assertHasErrors(['prompt']);
});
