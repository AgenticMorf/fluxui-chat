<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_conversations')) {
            return;
        }

        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('user_id', 30)->nullable()->index();
            $table->string('title');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('agent_conversation_messages', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('user_id', 30)->nullable()->index();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content');
            $table->text('attachments');
            $table->text('tool_calls');
            $table->text('tool_results');
            $table->text('usage');
            $table->text('meta');
            $table->timestamps();

            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
        });

        if (! Schema::hasTable('conversation_shares')) {
            Schema::create('conversation_shares', function (Blueprint $table) {
                $table->id();
                $table->string('conversation_id', 36)->index();
                $table->string('shareable_type');
                $table->string('shareable_id', 30);
                $table->timestamps();

                $table->index(['shareable_type', 'shareable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_shares');
        Schema::dropIfExists('agent_conversation_messages');
        Schema::dropIfExists('agent_conversations');
    }
};
