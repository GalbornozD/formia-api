<?php

namespace App\Models;

use App\Policies\CompanySettingsPolicy;
use Database\Factories\CompanySettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferencias regionales de una empresa (idioma, zona horaria, formatos).
 * Fila única por empresa — ver uq_company_settings_company.
 */
#[Fillable([
    'company_id',
    'timezone',
    'locale',
    'date_format',
    'time_format',
])]
#[UsePolicy(CompanySettingsPolicy::class)]
class CompanySettings extends Model
{
    /** @use HasFactory<CompanySettingsFactory> */
    use HasFactory;

    public $table = 'company_settings';

    public $timestamps = true;

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
