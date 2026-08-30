<?php

namespace App\Models;

use App\Policies\FormTypePolicy;
use Database\Factories\FormTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tipo de formulario configurado por una empresa (ej. "Inspeccion de
 * seguridad", "Reporte de incidente") -- catalogo propio, no compartido
 * entre empresas.
 */
#[Fillable(['company_id', 'name', 'description', 'status'])]
#[UsePolicy(FormTypePolicy::class)]
class FormType extends Model
{
    /** @use HasFactory<FormTypeFactory> */
    use HasFactory;

    public $table = 'form_types';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
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
     * @return HasMany<FormTypeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormTypeVersion::class, 'form_type_id');
    }

    /**
     * @return HasOne<FormTypeVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(FormTypeVersion::class, 'form_type_id')
            ->ofMany('version', 'max');
    }

    /**
     * @return HasOne<FormTypeVersion, $this>
     */
    public function latestPublishedVersion(): HasOne
    {
        return $this->hasOne(FormTypeVersion::class, 'form_type_id')
            ->ofMany(
                ['version' => 'max'],
                static function (Builder $query): void {
                    $query->where('is_published', true)
                        ->where('is_active', true);
                }
            );
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
