<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySettings>
 */
class CompanySettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'timezone' => 'America/Santiago',
            'locale' => 'es-CL',
            'date_format' => 'DD/MM/YYYY',
            'time_format' => 'HH:mm',
        ];
    }
}
