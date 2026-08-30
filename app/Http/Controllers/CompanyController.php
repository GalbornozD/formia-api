<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyBrandingService;
use App\Services\CompanySettingsService;
use App\Support\ApiResponse;
use App\Support\EmpresaContext;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyBrandingService $brandingService,
        private readonly CompanySettingsService $settingsService,
        private readonly EmpresaContext $context,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->companyArray($this->empresaActual()));
    }

    /**
     * Contexto completo para inicializar la aplicación (empresa + branding +
     * settings) en una sola llamada -- evita que cada pantalla pida su
     * propio branding por separado.
     */
    public function context(): JsonResponse
    {
        $empresa = $this->empresaActual();

        return ApiResponse::success([
            'company' => $this->companyArray($empresa),
            'branding' => $this->brandingService->present($this->brandingService->getOrCreate($empresa)),
            'settings' => $this->settingsService->present($this->settingsService->getOrCreate($empresa)),
        ]);
    }

    private function empresaActual(): Company
    {
        $empresa = $this->context->empresa();

        abort_if($empresa === null, 409, 'Selecciona una empresa activa (X-Empresa-Id) antes de continuar.');

        return $empresa;
    }

    /**
     * @return array<string, mixed>
     */
    private function companyArray(Company $empresa): array
    {
        return [
            'id' => $empresa->id,
            'uuid' => $empresa->uuid,
            'name' => $empresa->legal_name,
            'rut' => $empresa->rut,
            'status' => $empresa->status,
        ];
    }
}
