<?php

namespace AgenticMorf\FluxUIChat\Tests\Database\Factories;

use AgenticMorf\FluxUIChat\Tests\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => 'usr_'.Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => null,
        ];
    }
}
