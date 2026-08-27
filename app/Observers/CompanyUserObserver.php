<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\CompanyUser;
use App\Services\AuditLogger;

class CompanyUserObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(CompanyUser $companyUser): void
    {
        $this->registrar(AuditAction::MembresiaCreada, $companyUser, [
            'usuario_afectado_id' => $companyUser->user_id,
            'status' => $companyUser->status,
        ]);
    }

    public function updated(CompanyUser $companyUser): void
    {
        $this->registrar(AuditAction::MembresiaActualizada, $companyUser, [
            'usuario_afectado_id' => $companyUser->user_id,
            'cambios' => $companyUser->getChanges(),
        ]);
    }

    public function deleted(CompanyUser $companyUser): void
    {
        $this->registrar(AuditAction::MembresiaEliminada, $companyUser, [
            'usuario_afectado_id' => $companyUser->user_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function registrar(AuditAction $accion, CompanyUser $companyUser, array $detalle): void
    {
        $this->auditLogger->registrar(
            accion: $accion,
            usuario: request()->user(),
            empresaId: $companyUser->company_id,
            entidad: 'company_user',
            entidadId: "{$companyUser->user_id}:{$companyUser->company_id}",
            detalle: $detalle,
        );
    }
}
