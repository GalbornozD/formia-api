<?php

namespace Database\Factories;

use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormTypeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_type_version_id' => FormTypeVersion::factory(),
            'field_type_id' => FieldType::factory(),
            'parent_field_id' => null,
            'field_key' => Str::lower(fake()->unique()->lexify('field_????????')),
            'label' => fake()->words(3, true),
            'description' => null,
            'placeholder' => null,
            'default_value' => null,
            'is_required' => false,
            'is_readonly' => false,
            'is_hidden' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
            'width' => fake()->randomElement([3, 4, 6, 8, 9, 12]),
            'validation_rules' => null,
            'settings' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
