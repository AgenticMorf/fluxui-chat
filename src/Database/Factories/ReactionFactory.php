<?php

namespace AgenticMorf\FluxUIChat\Database\Factories;

use AgenticMorf\FluxUIChat\Models\AgentConversationMessage;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Maize\Markable\Models\Reaction;

/**
 * Factory for markable reactions (thumbs up/down) on chat messages.
 *
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

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
            'value' => 'thumbs_up',
            'metadata' => null,
        ];
    }

    public function thumbsUp(): static
    {
        return $this->state(fn (array $attributes) => ['value' => 'thumbs_up']);
    }

    public function thumbsDown(): static
    {
        return $this->state(fn (array $attributes) => ['value' => 'thumbs_down']);
    }

    /**
     * Set the user for this reaction.
     */
    public function forUser(Authenticatable|string $user): static
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    /**
     * Set the markable (e.g. AgentConversationMessage) for this reaction.
     */
    public function forMessage(AgentConversationMessage $message): static
    {
        return $this->state(fn (array $attributes) => [
            'markable_id' => $message->getKey(),
            'markable_type' => $message->getMorphClass(),
        ]);
    }
}
