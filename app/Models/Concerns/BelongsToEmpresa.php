<?php

namespace App\Models\Concerns;

use App\Models\Scopes\EmpresaScope;
use App\Support\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Úsalo en todo modelo de negocio con columna `company_id` (inspecciones,
 * reportes, etc.). Aplica el filtro WHERE company_id = :empresa_activa de
 * forma automática vía global scope, y rellena company_id al crear, para
 * que sea imposible olvidar el filtro (o el asignado) en un controlador.
 * Un usuario `master` (EmpresaContext::isMaster()) ve todas las empresas:
 * el scope no filtra nada para él.
 */
trait BelongsToEmpresa
{
    public static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope(new EmpresaScope);

        static::creating(function ($model) {
            if ($model->company_id !== null) {
                return;
            }

            $context = app(EmpresaContext::class);

            if ($context->empresaId() !== null) {
                $model->company_id = $context->empresaId();
            }
        });
    }

    /**
     * Escape hatch explícito para tareas administrativas/consola que sí
     * necesitan cruzar empresas — nunca lo uses en un controlador de request.
     */
    public function scopeSinEmpresaScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(EmpresaScope::class);
    }
}
