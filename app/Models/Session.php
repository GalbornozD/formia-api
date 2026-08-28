<?php

namespace App\Models;

use App\Casts\BinaryIp;
use Database\Factories\SessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de sesión/dispositivo asociado a la sesión web de Sanctum, con
 * la empresa activa elegida. No es el token de autenticación en sí (ese es
 * la cookie de sesión de Laravel); es el registro auditable de "qué
 * dispositivo, con qué empresa activa, desde cuándo" — visible para que el
 * usuario pueda revisar/cerrar sus sesiones (Ley 21.719, derecho a saber).
 * `revoked_at` sí tiene efecto real: VerificarSesionVigente corta la sesión
 * de Laravel asociada en el siguiente request (ver AuthService::CLAVE_SESION_ID).
 */
class Session extends Model
{
    /** @use HasFactory<SessionFactory> */
    use HasFactory;

    public $table = 'sessions';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'refresh_token_hash',
        'user_agent',
        'ip_address',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'ip_address' => BinaryIp::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function estaVigente(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
