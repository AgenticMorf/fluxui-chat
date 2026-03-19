# [agenticmorf/fluxui-chat](https://packagist.org/packages/agenticmorf/fluxui-chat)

Documentation is available on [GitHub Pages](https://agenticmorf.github.io/fluxui-chat/).

Flux UI chat interface for Laravel AI with streaming, model picker, and optional RAG.

## Requirements

- PHP ^8.2
- Laravel ^12.0
- laravel/ai ^0.2
- Livewire ^4.0
- maize-tech/laravel-markable

## Installation

```bash
composer require agenticmorf/fluxui-chat
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=fluxui-chat-config
```

Configure `config/fluxui-chat.php`:

- **route_prefix** — URL prefix (default: `chats`)
- **route_name_prefix** — Route name prefix (default: `chats.`)
- **middleware** — Route middleware (default: `web`, `auth`)
- **layout** — Livewire layout (default: `components.layouts.app.sidebar`)
- **agent_class** — Chat agent class
- **agent_instructions** — System prompt for the agent
- **anonymous_instructions** — System prompt for anonymous chat
- **rag** — RAG settings (enabled, top_k, cache_ttl)
- **accessible_bases_provider** — Implementation of `AccessibleBasesProvider` for RAG bases
- **chat_upload_service** — Implementation of `ChatUploadService` for file uploads
- **conversation_context_class** — Optional `MemoryContextProvider` for cross-conversation memory
- **preloaded_memory_key** — Optional container key for preloaded memory
- **cross_conversation_window_seconds** — Window for including messages from other conversations

## Routes

With default config, these routes are registered (with `web` and `auth` middleware):

- `GET /chat` — Redirects to chats index
- `GET /chats` — Chat index
- `GET /chats/bookmarks` — Bookmarked messages
- `GET /chats/new` — New conversation
- `GET /chats/anonymous` — Anonymous chat (no persistence)
- `GET /chats/{conversation}` — Show conversation
- `GET /chats/{conversation}/edit` — Edit conversation

## Optional Integrations

- **livewire/flux** — Flux UI components
- **livewire/flux-pro** — flux:composer component
- **agenticmorf/laravel-ai-model-manager** — Model picker in chat

## License

MIT
