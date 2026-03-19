<?php

use AgenticMorf\FluxUIChat\Agents\ChatAgent;
use AgenticMorf\FluxUIChat\Tests\TestCase;

uses(TestCase::class);

test('model returns default model', function () {
    expect(ChatAgent::model())->toBe('llama3.1:8b');
});

test('middleware returns empty array when not configured', function () {
    config(['fluxui-chat.agent_middleware' => []]);

    $agent = new ChatAgent;

    expect($agent->middleware())->toBeEmpty();
});

test('middleware returns resolved instances when configured', function () {
    $middlewareClass = stdClass::class;
    config(['fluxui-chat.agent_middleware' => [$middlewareClass]]);

    $agent = new ChatAgent;

    $middleware = $agent->middleware();
    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(stdClass::class);
});

test('tools returns empty array when not configured', function () {
    config(['fluxui-chat.agent_tools' => []]);

    $agent = new ChatAgent;

    expect($agent->tools())->toBeEmpty();
});

test('tools skips non-existent classes', function () {
    config(['fluxui-chat.agent_tools' => ['NonExistentClass123']]);

    $agent = new ChatAgent;

    expect($agent->tools())->toBeEmpty();
});

test('instructions uses config agent_instructions', function () {
    config(['fluxui-chat.agent_instructions' => 'You are a test assistant.']);

    $agent = ChatAgent::make();

    expect($agent->instructions())->toContain('You are a test assistant.');
});
