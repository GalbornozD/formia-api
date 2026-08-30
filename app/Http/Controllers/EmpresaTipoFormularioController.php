<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoFormulario\StoreTipoFormularioRequest;
use App\Http\Requests\TipoFormulario\UpdateTipoFormularioRequest;
use App\Models\Company;
use App\Models\FormType;
use App\Services\FormTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de tipos de formulario de una empresa. Autorizacion via
 * FormTypePolicy: master gestiona cualquier empresa, administrador solo la
 * suya -- mismo criterio de rol que EmpresaUsuarioController.
 */
class EmpresaTipoFormularioController extends Controller
{
    public function __construct(private readonly FormTypeService $formTypeService) {}

    public function index(Company $empresa): JsonResponse
    {
        $this->authorize('viewAny', [FormType::class, $empresa]);

        $tipos = FormType::where('company_id', $empresa->id)
            ->orderBy('name')
            ->get(['id', 'company_id', 'name', 'description', 'status']);

        return ApiResponse::success($tipos->map(fn (FormType $tipo) => $this->tipoArray($tipo)));
    }

    public function store(StoreTipoFormularioRequest $request, Company $empresa): JsonResponse
    {
        $this->authorize('create', [FormType::class, $empresa]);

        $tipo = $this->formTypeService->create($empresa, [
            'name' => $request->validated('nombre'),
            'description' => $request->validated('descripcion'),
        ], $request->user());

        return ApiResponse::success($this->tipoArray($tipo), 201);
    }

    public function update(UpdateTipoFormularioRequest $request, Company $empresa, FormType $tipoFormulario): JsonResponse
    {
        abort_unless($tipoFormulario->company_id === $empresa->id, 404);

        $this->authorize('update', $tipoFormulario);

        $tipoFormulario = $this->formTypeService->update(
            $tipoFormulario,
            $request->datosParaGuardar(),
            $request->user(),
        );

        return ApiResponse::success($this->tipoArray($tipoFormulario));
    }

    public function destroy(Company $empresa, FormType $tipoFormulario): JsonResponse
    {
        abort_unless($tipoFormulario->company_id === $empresa->id, 404);

        $this->authorize('delete', $tipoFormulario);

        $this->formTypeService->deactivate($tipoFormulario, request()->user());

        return ApiResponse::success();
    }

    /**
     * @return array<string, mixed>
     */
    private function tipoArray(FormType $tipo): array
    {
        return [
            'id' => $tipo->id,
            'company_id' => $tipo->company_id,
            'name' => $tipo->name,
            'description' => $tipo->description,
            'status' => $tipo->status,
        ];
    }
}
