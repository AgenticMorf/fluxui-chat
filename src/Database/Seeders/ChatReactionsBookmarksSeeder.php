<?php

namespace AgenticMorf\FluxUIChat\Database\Seeders;

use AgenticMorf\FluxUIChat\Database\Factories\BookmarkFactory;
use AgenticMorf\FluxUIChat\Database\Factories\ReactionFactory;
use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChatReactionsBookmarksSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = config('markable.user_model');
        if (! $userModel) {
            $this->command->warn('ChatReactionsBookmarksSeeder: No user model configured. Skipping.');

            return;
        }
        $users = $userModel::query()->limit(5)->get();
        if ($users->isEmpty()) {
            $this->command->warn('ChatReactionsBookmarksSeeder: No users found. Skipping.');

            return;
        }

        $messages = AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->get();

        if ($messages->isEmpty()) {
            $this->command->warn('ChatReactionsBookmarksSeeder: No assistant messages found. Skipping.');

            return;
        }

        foreach ($users as $user) {
            if (config('database.default') === 'pgsql') {
                DB::statement("SELECT set_config('app.current_team_id', '', true)");
                DB::statement("SELECT set_config('app.current_user_id', ?, true)", [$user->id]);
            }

            $sample = $messages->shuffle()->take(max(1, (int) ceil($messages->count() * 0.4)));

            foreach ($sample as $message) {
                $action = random_int(1, 10);
                if ($action <= 4) {
                    ReactionFactory::new()
                        ->forUser($user)
                        ->forMessage($message)
                        ->thumbsUp()
                        ->create();
                } elseif ($action <= 6) {
                    ReactionFactory::new()
                        ->forUser($user)
                        ->forMessage($message)
                        ->thumbsDown()
                        ->create();
                } elseif ($action <= 8) {
                    BookmarkFactory::new()
                        ->forUser($user)
                        ->forMessage($message)
                        ->create();
                }
            }
        }
    }
}
