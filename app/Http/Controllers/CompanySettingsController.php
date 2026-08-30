<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanySettings\UpdateCompanySettingsRequest;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Services\CompanySettingsService;
use App\Support\ApiResponse;
use App\Support\EmpresaContext;
use Illuminate\Http\JsonResponse;

class CompanySettingsController extends Controller
{
    public function __construct(
        private readonly CompanySettingsService $settingsService,
        private readonly EmpresaContext $context,
    ) {}

    public function show(): JsonResponse
    {
        $settings = $this->settingsActual();
        $this->authorize('view', $settings);

        return ApiResponse::success($this->settingsService->present($settings));
    }

    public function update(UpdateCompanySettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsActual();
        $this->authorize('update', $settings);

        $settings = $this->settingsService->update($settings, $request->toData(), $request->user());

        return ApiResponse::success($this->settingsService->present($settings));
    }

    private function settingsActual(): CompanySettings
    {
        return $this->settingsService->getOrCreate($this->empresaActual());
    }

    private function empresaActual(): Company
    {
        $empresa = $this->context->empresa();

        abort_if($empresa === null, 409, 'Selecciona una empresa activa (X-Empresa-Id) antes de gestionar su configuración.');

        return $empresa;
    }
}
