<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Punto único de escritura en audit_logs. Se apoya en el request actual
 * para capturar la IP y evita que cada caller tenga que repetir ese detalle.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $detalle
     */
    public function registrar(
        AuditAction $accion,
        ?User $usuario = null,
        ?int $empresaId = null,
        ?string $entidad = null,
        ?string $entidadId = null,
        array $detalle = [],
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $usuario?->id,
            'company_id' => $empresaId,
            'action' => $accion->value,
            'entity' => $entidad,
            'entity_id' => $entidadId,
            'details' => $detalle === [] ? null : $detalle,
            'ip_address' => $this->request->ip(),
        ]);
    }
}
