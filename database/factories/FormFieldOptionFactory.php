<?php

namespace Database\Factories;

use App\Models\FormField;
use App\Models\FormFieldOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormFieldOption>
 */
class FormFieldOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_field_id' => FormField::factory(),
            'option_value' => Str::lower(fake()->unique()->lexify('option_????????')),
            'option_label' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_default' => false,
            'is_active' => true,
            'settings' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
