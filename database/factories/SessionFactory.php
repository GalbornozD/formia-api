<?php

namespace Database\Factories;

use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'refresh_token_hash' => hash('sha256', fake()->unique()->uuid()),
            'user_agent' => fake()->userAgent(),
            'ip_address' => fake()->ipv4(),
            'expires_at' => now()->addMinutes(120),
            'revoked_at' => null,
        ];
    }

    public function revocada(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expirada(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
