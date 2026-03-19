<?php

use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;

dataset('date_range_filters', [
    '7 days' => ['7d', 12],
    '30 days' => ['30d', 35],
    '90 days' => ['90d', 95],
]);
use AgenticMorf\FluxUIChat\Services\ChatService;
use AgenticMorf\FluxUIChat\Tests\Models\User;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maize\Markable\Models\Bookmark;

test('getAccessibleConversations returns owned conversations', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, ['msg_'.Str::ulid()]);

    $chatService = app(ChatService::class);
    $paginator = $chatService->getAccessibleConversations($user);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($conversationId)
        ->and($paginator->items()[0]->title)->toContain('Test Chat');
});

test('getAccessibleConversations filters by search', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $con1 = 'con_'.Str::ulid();
    $con2 = 'con_'.Str::ulid();
    DB::table('agent_conversations')->insert([
        ['id' => $con1, 'user_id' => $user->id, 'title' => 'Alpha Chat', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $con2, 'user_id' => $user->id, 'title' => 'Beta Chat', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $chatService = app(ChatService::class);
    $result = $chatService->getAccessibleConversations($user, ['search' => 'Alpha']);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->title)->toBe('Alpha Chat');
});

test('getAccessibleConversations filters by date range', function (string $dateRange, int $daysBack) {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $oldId = 'con_'.Str::ulid();
    $newId = 'con_'.Str::ulid();
    DB::table('agent_conversations')->insert([
        ['id' => $oldId, 'user_id' => $user->id, 'title' => 'Old', 'created_at' => now()->subDays($daysBack + 5), 'updated_at' => now()->subDays($daysBack + 5)],
        ['id' => $newId, 'user_id' => $user->id, 'title' => 'New', 'created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1)],
    ]);

    $chatService = app(ChatService::class);
    $result = $chatService->getAccessibleConversations($user, ['date_range' => $dateRange]);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($newId);
})->with('date_range_filters');

test('getConversationTitle returns title for owned conversation', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'My Chat Title',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $chatService = app(ChatService::class);

    expect($chatService->getConversationTitle($conversationId, $user))->toBe('My Chat Title');
});

test('getConversationTitle returns null for empty id', function () {
    $user = User::factory()->create();
    $chatService = app(ChatService::class);

    expect($chatService->getConversationTitle('', $user))->toBeNull()
        ->and($chatService->getConversationTitle(null, $user))->toBeNull();
});

test('getConversationTitle returns null for unauthorized conversation', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->setRlsContext($owner->id);

    $conversationId = 'con_'.Str::ulid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $owner->id,
        'title' => 'Owner Chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $chatService = app(ChatService::class);

    expect($chatService->getConversationTitle($conversationId, $otherUser))->toBeNull();
});

test('getMarkStateForMessages returns empty when message ids empty', function () {
    $user = User::factory()->create();
    $chatService = app(ChatService::class);

    $state = $chatService->getMarkStateForMessages([], $user);

    expect($state)->toBe([
        'thumbs_up' => [],
        'thumbs_down' => [],
        'bookmarked' => [],
    ]);
});

test('cloneConversation with custom title', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $msg1 = 'msg_'.Str::ulid();
    $this->createConversationWithMessages($user->id, $conversationId, [$msg1]);

    $chatService = app(ChatService::class);
    $newId = $chatService->cloneConversation($conversationId, $user, false, 'Custom Title');

    $newConv = DB::table('agent_conversations')->where('id', $newId)->first();
    expect($newConv->title)->toBe('Custom Title');
});

test('validateConversationOwnership throws for non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->setRlsContext($owner->id);

    $conversationId = 'con_'.Str::ulid();
    $this->createConversationWithMessages($owner->id, $conversationId, ['msg_'.Str::ulid()]);

    $chatService = app(ChatService::class);

    expect(fn () => $chatService->validateConversationOwnership($conversationId, $otherUser))
        ->toThrow(AuthorizationException::class);
});

test('getBookmarkedMessages returns empty when user has no bookmarks', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $chatService = app(ChatService::class);
    $result = $chatService->getBookmarkedMessages($user);

    expect($result->total())->toBe(0)
        ->and($result->items())->toBeEmpty();
});

test('getBookmarkedMessages returns bookmarked messages from accessible conversations', function () {
    $user = User::factory()->create();
    $this->setRlsContext($user->id);

    $conversationId = 'con_'.Str::ulid();
    $messageId = 'msg_'.Str::ulid();
    $this->createConversationAndMessage($user->id, $conversationId, $messageId);

    $message = AgentConversationMessage::find($messageId);
    Bookmark::add($message, $user);

    $chatService = app(ChatService::class);
    $result = $chatService->getBookmarkedMessages($user);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($messageId)
        ->and($result->items()[0]->conversation_id)->toBe($conversationId);
});
