<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
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
            'legal_name' => fake()->unique()->company(),
            'rut' => $this->rutFalso(),
            'status' => true,
        ];
    }

    private function rutFalso(): string
    {
        $numero = fake()->unique()->numberBetween(1_000_000, 99_999_999);

        return $numero.'-'.fake()->randomElement(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'K']);
    }
}
