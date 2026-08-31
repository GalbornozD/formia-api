<?php

namespace App\Models;

use App\Casts\BinaryIp;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['uuid', 'email', 'first_name', 'last_name', 'phone'])]
#[Hidden(['password_hash', 'rut_encrypted', 'mfa_secret_encrypted'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public $table = 'users';

    /**
     * Sanctum/EloquentUserProvider leen esta columna en vez de `password`:
     * el hash vive en `password_hash` para calzar con el esquema entregado.
     */
    protected $authPasswordName = 'password_hash';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'password_updated_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'status' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'last_login_ip' => BinaryIp::class,
        ];
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id')
            ->using(CompanyUser::class)
            ->withPivot(['permission', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CompanyUser, $this>
     */
    public function membresias(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    /**
     * @return HasMany<Session, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Session::class, 'user_id');
    }

    /**
     * @return HasMany<PasswordResetToken, $this>
     */
    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class, 'user_id');
    }

    /**
     * @return HasMany<FormAssignment, $this>
     */
    public function formAssignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class, 'user_id');
    }

    /**
     * @return HasMany<FormResponse, $this>
     */
    public function formResponses(): HasMany
    {
        return $this->hasMany(FormResponse::class, 'user_id');
    }

    public function membresiaActivaEn(int $empresaId): ?CompanyUser
    {
        return $this->membresias()
            ->where('company_id', $empresaId)
            ->where('status', true)
            ->first();
    }

    public function estaBloqueado(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Rol global (users.role_id) — nunca por empresa. Acceso sin
     * restricción de empresa activa.
     */
    public function esMaster(): bool
    {
        return $this->role_id === Role::MASTER;
    }

    /**
     * Rol global (users.role_id). Gestiona usuarios/configuración, pero
     * siempre acotado a la empresa activa (EmpresaContext).
     */
    public function esAdministrador(): bool
    {
        return $this->role_id === Role::ADMINISTRADOR;
    }
}
