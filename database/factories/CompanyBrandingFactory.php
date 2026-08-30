<?php

namespace Database\Factories;

use App\Enums\ThemeMode;
use App\Models\Company;
use App\Models\CompanyBranding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyBranding>
 */
class CompanyBrandingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'primary_color' => '#2563EB',
            'secondary_color' => '#0F172A',
            'accent_color' => null,
            'theme_mode' => ThemeMode::Light,
            'version' => 1,
        ];
    }
}
