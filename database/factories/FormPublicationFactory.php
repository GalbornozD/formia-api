<?php

namespace Database\Factories;

use App\Enums\RespondentType;
use App\Models\Company;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormPublication>
 */
class FormPublicationFactory extends Factory
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
            'company_id' => Company::factory(),
            'form_type_id' => fn (array $attributes): int => FormType::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'form_type_version_id' => fn (array $attributes): int => FormTypeVersion::factory()
                ->published()
                ->create(['form_type_id' => $attributes['form_type_id']])
                ->id,
            'name' => fake()->words(3, true),
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'respondent_type' => RespondentType::Anonymous,
            'starts_at' => null,
            'ends_at' => null,
            'allow_draft' => true,
            'allow_edit_after_submit' => false,
            'show_progress' => true,
            'show_question_numbers' => true,
            'max_responses_per_respondent' => 1,
            'thank_you_title' => 'Gracias por responder',
            'thank_you_description' => null,
            'is_active' => true,
        ];
    }

    public function authenticatedOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'respondent_type' => RespondentType::User,
        ]);
    }

    public function guestOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'respondent_type' => RespondentType::Guest,
        ]);
    }

    public function anonymousOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'respondent_type' => RespondentType::Anonymous,
        ]);
    }
}
