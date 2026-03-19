---
title: Usage
---

# Usage

## Routes

With default config:

- `GET /chat` — Redirects to `/chats`
- `GET /chats` — Chat index (list conversations)
- `GET /chats/bookmarks` — Bookmarked messages
- `GET /chats/new` — Start new conversation
- `GET /chats/anonymous` — Anonymous chat (no persistence)
- `GET /chats/{conversation}` — View conversation
- `GET /chats/{conversation}/edit` — Edit conversation title

## Blade Components

Published components:

- `chats.layout` — Chat layout with nav
- `chats.table` — Conversation table

## Contracts

| Contract | Purpose |
|----------|---------|
| RagContextProvider | Provide RAG context for messages |
| MemoryContextProvider | Cross-conversation memory search |
| AccessibleBasesProvider | Bases user can attach for RAG |
| ChatUploadService | Process file uploads and create attachments |

## Anonymous Chat

`/chats/anonymous` runs without auth. Nothing is persisted. Use for demos or unauthenticated support.
