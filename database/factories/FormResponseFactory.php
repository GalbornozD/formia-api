<?php

namespace Database\Factories;

use App\Enums\FormResponseStatus;
use App\Enums\RespondentType;
use App\Models\FormAssignment;
use App\Models\FormPublication;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormResponse>
 */
class FormResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_publication_id' => FormPublication::factory(),
            'company_id' => fn (array $attributes): int => FormPublication::query()
                ->findOrFail($attributes['form_publication_id'])
                ->company_id,
            'form_type_version_id' => fn (array $attributes): int => FormPublication::query()
                ->findOrFail($attributes['form_publication_id'])
                ->form_type_version_id,
            'form_assignment_id' => null,
            'respondent_type' => RespondentType::User,
            'user_id' => User::factory(),
            'guest_respondent_id' => null,
            'status' => FormResponseStatus::Draft,
            'started_at' => now(),
            'last_saved_at' => now(),
            'submitted_at' => null,
            'access_token_hash' => null,
            'locale' => 'es-CL',
        ];
    }

    public function forAssignment(FormAssignment $assignment): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $assignment->company_id,
            'form_publication_id' => $assignment->form_publication_id,
            'form_assignment_id' => $assignment->id,
            'user_id' => $assignment->user_id,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FormResponseStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
