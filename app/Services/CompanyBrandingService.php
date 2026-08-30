<?php

namespace App\Services;

use App\Enums\BrandingAssetType;
use App\Enums\ThemeMode;
use App\Models\Company;
use App\Models\CompanyBranding;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CompanyBrandingService
{
    private const DISK = 'public';

    /**
     * Devuelve el branding de la empresa, creándolo con los valores por
     * defecto de la migración si todavía no existe. `createOrFirst` es
     * seguro ante creación concurrente (uq_company_branding_company).
     */
    public function getOrCreate(Company $company): CompanyBranding
    {
        return CompanyBranding::createOrFirst(
            ['company_id' => $company->id],
            [
                'primary_color' => '#2563EB',
                'secondary_color' => '#0F172A',
                'accent_color' => null,
                'theme_mode' => ThemeMode::Light,
                'version' => 1,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyBranding $branding, array $data, User $actor): CompanyBranding
    {
        return DB::transaction(function () use ($branding, $data, $actor): CompanyBranding {
            $branding = CompanyBranding::query()->lockForUpdate()->findOrFail($branding->id);

            $branding->forceFill([...$data, 'updated_by' => $actor->id]);
            $branding->version = $branding->version + 1;
            $branding->save();

            return $branding->refresh();
        });
    }

    public function updateAsset(CompanyBranding $branding, BrandingAssetType $type, UploadedFile $file, User $actor): CompanyBranding
    {
        return DB::transaction(function () use ($branding, $type, $file, $actor): CompanyBranding {
            $branding = CompanyBranding::query()->lockForUpdate()->findOrFail($branding->id);
            $previousPath = $branding->{$type->column()};

            // extension() usa el MIME real detectado, no el nombre original
            // del archivo -- el nombre en disco nunca lo controla el usuario.
            $path = $file->storeAs(
                $this->directoryFor($branding->company_id),
                $type->slug().'.'.$file->extension(),
                self::DISK,
            );

            $branding->forceFill([$type->column() => $path, 'updated_by' => $actor->id]);
            $branding->version = $branding->version + 1;
            $branding->save();

            if ($previousPath !== null && $previousPath !== $path) {
                Storage::disk(self::DISK)->delete($previousPath);
            }

            return $branding->refresh();
        });
    }

    public function removeAsset(CompanyBranding $branding, BrandingAssetType $type, User $actor): CompanyBranding
    {
        return DB::transaction(function () use ($branding, $type, $actor): CompanyBranding {
            $branding = CompanyBranding::query()->lockForUpdate()->findOrFail($branding->id);
            $previousPath = $branding->{$type->column()};

            $branding->forceFill([$type->column() => null, 'updated_by' => $actor->id]);
            $branding->version = $branding->version + 1;
            $branding->save();

            if ($previousPath !== null) {
                Storage::disk(self::DISK)->delete($previousPath);
            }

            return $branding->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CompanyBranding $branding): array
    {
        return [
            'logo' => $this->url($branding->logo_path),
            'logoDark' => $this->url($branding->logo_dark_path),
            'logoCompact' => $this->url($branding->logo_compact_path),
            'favicon' => $this->url($branding->favicon_path),
            'primaryColor' => $branding->primary_color,
            'secondaryColor' => $branding->secondary_color,
            'accentColor' => $branding->accent_color,
            'themeMode' => $branding->theme_mode->value,
            'version' => $branding->version,
        ];
    }

    private function directoryFor(int $companyId): string
    {
        return "companies/{$companyId}/branding";
    }

    private function url(?string $path): ?string
    {
        return $path !== null ? Storage::disk(self::DISK)->url($path) : null;
    }
}
