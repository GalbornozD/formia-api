<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\User;

final class CompanySettingsService
{
    /**
     * Devuelve las preferencias regionales de la empresa, creándolas con los
     * valores por defecto de la migración si todavía no existen.
     * `createOrFirst` es seguro ante creación concurrente
     * (uq_company_settings_company).
     */
    public function getOrCreate(Company $company): CompanySettings
    {
        return CompanySettings::createOrFirst(
            ['company_id' => $company->id],
            [
                'timezone' => 'America/Santiago',
                'locale' => 'es-CL',
                'date_format' => 'DD/MM/YYYY',
                'time_format' => 'HH:mm',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanySettings $settings, array $data, User $actor): CompanySettings
    {
        $settings->forceFill([...$data, 'updated_by' => $actor->id])->save();

        return $settings->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CompanySettings $settings): array
    {
        return [
            'timezone' => $settings->timezone,
            'locale' => $settings->locale,
            'dateFormat' => $settings->date_format,
            'timeFormat' => $settings->time_format,
        ];
    }
}
