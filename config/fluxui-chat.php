<?php

use AgenticMorf\FluxUIChat\Agents\ChatAgent;

return [
    'accessible_bases_provider' => null,
    'chat_upload_service' => null,
    'route_prefix' => 'chats',
    'route_name_prefix' => 'chats.',
    'middleware' => ['web', 'auth'],
    'layout' => 'components.layouts.app.sidebar',
    'agent_class' => ChatAgent::class,
    'agent_middleware' => [],
    'agent_tools' => [],
    'conversation_context_class' => null,
    'preloaded_memory_key' => null,
    'cross_conversation_window_seconds' => 0,
    'agent_instructions' => 'You are a helpful assistant. Answer questions based on the context provided when available.',
    'anonymous_instructions' => 'You are a helpful assistant in an anonymous chat session. This is an anonymous chat: nothing is saved or remembered outside of this current conversation. You cannot access the user\'s name, previous conversations, or any information beyond what they share in this session. If asked about their identity or past interactions, explain that you only have access to this current chat and cannot remember anything outside it.',
    'rag' => [
        'enabled' => false,
        'top_k' => 10,
        'cache_ttl' => 600,
    ],
];
