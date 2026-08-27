<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'role_id' => Role::ADMINISTRADOR,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password_hash' => 'password12345',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => true,
        ];
    }

    public function master(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::MASTER,
        ]);
    }

    public function pendienteVerificacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_until' => now()->addMinutes(30),
            'failed_login_attempts' => 5,
        ]);
    }
}
