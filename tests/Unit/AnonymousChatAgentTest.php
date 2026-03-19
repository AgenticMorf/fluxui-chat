<?php

use AgenticMorf\FluxUIChat\Agents\AnonymousChatAgent;
use AgenticMorf\FluxUIChat\Tests\TestCase;
use Laravel\Ai\Messages\Message;

uses(TestCase::class);

test('forSession builds agent with conversation history', function () {
    $history = [
        (object) ['role' => 'user', 'content' => 'Hello'],
        (object) ['role' => 'assistant', 'content' => 'Hi there'],
    ];

    $agent = AnonymousChatAgent::forSession($history);

    $messages = iterator_to_array($agent->messages());
    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->role->value)->toBe('user')
        ->and($messages[0]->content)->toBe('Hello')
        ->and($messages[1]->role->value)->toBe('assistant')
        ->and($messages[1]->content)->toBe('Hi there');
});

test('forSession handles empty history', function () {
    $agent = AnonymousChatAgent::forSession([]);

    expect(iterator_to_array($agent->messages()))->toBeEmpty();
});

test('forSession uses user role when role is missing', function () {
    $agent = AnonymousChatAgent::forSession([
        (object) ['content' => 'Just content'],
    ]);

    $messages = iterator_to_array($agent->messages());
    expect($messages[0]->role->value)->toBe('user')
        ->and($messages[0]->content)->toBe('Just content');
});

test('instructions returns config value when set', function () {
    config(['fluxui-chat.anonymous_instructions' => 'Custom anonymous instructions.']);

    $agent = new AnonymousChatAgent([]);

    expect($agent->instructions())->toBe('Custom anonymous instructions.');
});

test('instructions returns default when config is empty', function () {
    config(['fluxui-chat.anonymous_instructions' => '']);

    $agent = new AnonymousChatAgent([]);

    $instructions = $agent->instructions();
    expect($instructions)->toContain('anonymous chat')
        ->and($instructions)->toContain('nothing is saved or remembered');
});

test('tools returns empty array', function () {
    $agent = new AnonymousChatAgent([]);

    expect(iterator_to_array($agent->tools()))->toBeEmpty();
});
