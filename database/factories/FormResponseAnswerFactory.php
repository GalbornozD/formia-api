<?php

namespace Database\Factories;

use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\FormResponseAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormResponseAnswer>
 */
class FormResponseAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_response_id' => FormResponse::factory(),
            'form_field_id' => FormField::factory(),
            'value_json' => fake()->words(3, true),
        ];
    }
}
