<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\GuestRespondent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestRespondent>
 */
class GuestRespondentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'id' => Str::uuid()->toString(),
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => $email,
            'phone' => fake()->phoneNumber(),
            'whatsapp_phone' => null,
            'external_reference' => null,
            // Misma fórmula que GuestRespondentService/FormResponseService:
            // sha256(company_id|email) — antes este factory no incluía el
            // company_id y quedaba inconsistente con el código real.
            'identity_hash' => fn (array $attributes) => hash('sha256', $attributes['company_id'].'|'.strtolower($email)),
            'metadata' => null,
            'status' => true,
        ];
    }
}
