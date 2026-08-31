<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\RespondentType;
use App\Models\FormAssignment;
use App\Models\FormPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormAssignment>
 */
class FormAssignmentFactory extends Factory
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
            'form_publication_id' => FormPublication::factory(),
            'company_id' => fn (array $attributes): int => FormPublication::query()
                ->findOrFail($attributes['form_publication_id'])
                ->company_id,
            'respondent_type' => RespondentType::User,
            'user_id' => User::factory(),
            'guest_respondent_id' => null,
            'status' => AssignmentStatus::Pending,
            'assigned_at' => now(),
            'started_at' => null,
            'submitted_at' => null,
            'created_by' => null,
        ];
    }
}
