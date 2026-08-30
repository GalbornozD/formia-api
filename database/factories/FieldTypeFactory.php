<?php

namespace Database\Factories;

use App\Models\FieldType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FieldType>
 */
class FieldTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::lower(fake()->unique()->lexify('type_????????')),
            'name' => fake()->unique()->words(2, true),
            'has_options' => false,
            'is_container' => false,
            'is_active' => true,
        ];
    }

    public function withOptions(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_options' => true,
        ]);
    }

    public function container(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_container' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
