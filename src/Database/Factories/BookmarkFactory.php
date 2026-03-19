<?php

namespace AgenticMorf\FluxUIChat\Database\Factories;

use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Maize\Markable\Models\Bookmark;

/**
 * Factory for markable bookmarks on chat messages.
 *
 * @extends Factory<Bookmark>
 */
class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'markable_id' => null,
            'markable_type' => AgentConversationMessage::class,
            'value' => null,
            'metadata' => null,
        ];
    }

    /**
     * Set the user for this bookmark.
     */
    public function forUser(Authenticatable|string $user): static
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    /**
     * Set the markable (e.g. AgentConversationMessage) for this bookmark.
     */
    public function forMessage(AgentConversationMessage $message): static
    {
        return $this->state(fn (array $attributes) => [
            'markable_id' => $message->getKey(),
            'markable_type' => $message->getMorphClass(),
        ]);
    }
}
