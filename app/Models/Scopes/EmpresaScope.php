<?php

namespace App\Models\Scopes;

use App\Support\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Aísla cada modelo de negocio a la empresa activa del request actual.
 *
 * Falla cerrado a propósito: si no hay empresa resuelta en el contexto
 * (por ejemplo un job en cola sin request HTTP, o un middleware mal
 * ordenado), la scope bloquea la tabla completa en vez de arriesgarse a
 * devolver filas de todas las empresas. Única excepción: un usuario
 * `master`, que por diseño ve todas las empresas sin filtro.
 */
class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(EmpresaContext::class);

        if ($context->isMaster()) {
            return;
        }

        if ($context->resolved()) {
            $builder->where($model->qualifyColumn('company_id'), $context->empresaId());

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
