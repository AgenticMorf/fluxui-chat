<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('markable_reactions')) {
            return;
        }

        $userClass = config('markable.user_model');
        $userTable = $userClass ? (new $userClass)->getTable() : 'users';

        Schema::create('markable_reactions', function (Blueprint $table) use ($userTable) {
            $table->id();
            $table->string('user_id', 30)->index();
            $table->string('markable_id', 30);
            $table->string('markable_type');
            $table->string('value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['markable_type', 'markable_id']);
            $table->foreign('user_id')->references('id')->on($userTable)->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markable_reactions');
    }
};
