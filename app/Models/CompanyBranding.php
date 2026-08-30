<?php

namespace App\Models;

use App\Enums\ThemeMode;
use App\Policies\CompanyBrandingPolicy;
use Database\Factories\CompanyBrandingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Branding visual de una empresa (logos, colores, tema). Fila única por
 * empresa — ver uq_company_branding_company. `version` se incrementa en
 * cada cambio (CompanyBrandingService) para invalidar cache de frontend.
 */
#[Fillable([
    'company_id',
    'logo_path',
    'logo_dark_path',
    'logo_compact_path',
    'favicon_path',
    'primary_color',
    'secondary_color',
    'accent_color',
    'theme_mode',
    'version',
])]
#[UsePolicy(CompanyBrandingPolicy::class)]
class CompanyBranding extends Model
{
    /** @use HasFactory<CompanyBrandingFactory> */
    use HasFactory;

    public $table = 'company_branding';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'theme_mode' => ThemeMode::class,
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
