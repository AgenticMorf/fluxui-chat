<?php

namespace AgenticMorf\FluxUIChat\Models;

use Illuminate\Database\Eloquent\Model;
use Maize\Markable\Markable;
use Maize\Markable\Models\Bookmark;
use Maize\Markable\Models\Reaction;

class AgentConversationMessage extends Model
{
    use Markable;

    protected static array $marks = [Reaction::class, Bookmark::class];

    protected $table = 'agent_conversation_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'user_id',
        'agent',
        'role',
        'content',
        'attachments',
        'tool_calls',
        'tool_results',
        'usage',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
