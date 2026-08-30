<?php

namespace Database\Factories;

use App\Models\FormType;
use App\Models\FormTypeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTypeVersion>
 */
class FormTypeVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_type_id' => FormType::factory(),
            'version' => 1,
            'is_published' => false,
            'is_active' => true,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
