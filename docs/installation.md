---
title: Installation
---

# Installation

## Requirements

- PHP ^8.2
- Laravel ^12.0
- laravel/ai ^0.2
- Livewire ^4.0
- maize-tech/laravel-markable

## Composer

```bash
composer require agenticmorf/fluxui-chat
```

[GitHub](https://github.com/AgenticMorf/fluxui-chat)

## Layout

The package expects a layout at `components.layouts.app.sidebar`. Ensure your app provides it or override via config:

```php
'layout' => 'components.layouts.app.sidebar',
```

## Migrations

Migrations for reactions and bookmarks are loaded automatically. Run:

```bash
php artisan migrate
```
