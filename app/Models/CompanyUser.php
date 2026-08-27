<?php

namespace App\Models;

use App\Observers\CompanyUserObserver;
use App\Policies\CompanyUserPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membresía usuario-empresa: bitmask `permission` para autorización fina
 * por módulo (a definir más adelante). El rol (master/administrador) vive
 * en users.role_id, nunca aquí — es global al usuario, no por empresa.
 */
#[ObservedBy(CompanyUserObserver::class)]
#[UsePolicy(CompanyUserPolicy::class)]
class CompanyUser extends Pivot
{
    public $table = 'company_user';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'permission',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'permission' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function tienePermiso(int $bit): bool
    {
        return ($this->permission & $bit) === $bit;
    }

    public function estaActiva(): bool
    {
        return (bool) $this->status;
    }
}
