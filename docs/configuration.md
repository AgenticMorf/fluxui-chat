---
title: Configuration
---

# Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=fluxui-chat-config
```

## Options

| Key | Default | Description |
|-----|---------|-------------|
| route_prefix | chats | URL prefix for chat routes |
| route_name_prefix | chats. | Route name prefix |
| middleware | web, auth | Route middleware |
| layout | components.layouts.app.sidebar | Livewire layout |
| agent_class | ChatAgent::class | Chat agent class |
| agent_instructions | ... | System prompt for authenticated chat |
| anonymous_instructions | ... | System prompt for anonymous chat |
| rag.enabled | false | Enable RAG context |
| rag.top_k | 10 | RAG result limit |
| rag.cache_ttl | 600 | RAG cache TTL (seconds) |
| accessible_bases_provider | null | AccessibleBasesProvider implementation |
| chat_upload_service | null | ChatUploadService implementation |
| conversation_context_class | null | MemoryContextProvider for cross-conversation memory |
| preloaded_memory_key | null | Container key for preloaded memory |
| cross_conversation_window_seconds | 0 | Include messages from other conversations within this window |

## RAG

Bind `RagContextProvider` and enable RAG:

```php
// In a service provider
$this->app->bind(\AgenticMorf\FluxUIChat\Contracts\RagContextProvider::class, YourRagProvider::class);

// config/fluxui-chat.php
'rag' => [
    'enabled' => true,
    'top_k' => 10,
],
```

## File Uploads

Bind `ChatUploadService` and `AccessibleBasesProvider`:

```php
$this->app->bind(\AgenticMorf\FluxUIChat\Contracts\ChatUploadService::class, YourUploadService::class);
$this->app->bind(\AgenticMorf\FluxUIChat\Contracts\AccessibleBasesProvider::class, YourBasesProvider::class);

// config/fluxui-chat.php
'chat_upload_service' => YourUploadService::class,
'accessible_bases_provider' => YourBasesProvider::class,
```
