<?php

namespace App\Http\Controllers;

use App\Enums\BrandingAssetType;
use App\Http\Requests\CompanyBranding\UpdateCompanyBrandingRequest;
use App\Http\Requests\CompanyBranding\UploadCompanyFaviconRequest;
use App\Http\Requests\CompanyBranding\UploadCompanyLogoRequest;
use App\Models\Company;
use App\Models\CompanyBranding;
use App\Services\CompanyBrandingService;
use App\Support\ApiResponse;
use App\Support\EmpresaContext;
use Illuminate\Http\JsonResponse;

class CompanyBrandingController extends Controller
{
    public function __construct(
        private readonly CompanyBrandingService $brandingService,
        private readonly EmpresaContext $context,
    ) {}

    public function show(): JsonResponse
    {
        $branding = $this->brandingActual();
        $this->authorize('view', $branding);

        return ApiResponse::success($this->brandingService->present($branding));
    }

    public function update(UpdateCompanyBrandingRequest $request): JsonResponse
    {
        $branding = $this->brandingActual();
        $this->authorize('update', $branding);

        $branding = $this->brandingService->update($branding, $request->toData(), $request->user());

        return ApiResponse::success($this->brandingService->present($branding));
    }

    public function uploadLogo(UploadCompanyLogoRequest $request): JsonResponse
    {
        return $this->subirActivo($request, BrandingAssetType::Logo);
    }

    public function deleteLogo(): JsonResponse
    {
        return $this->eliminarActivo(BrandingAssetType::Logo);
    }

    public function uploadLogoCompact(UploadCompanyLogoRequest $request): JsonResponse
    {
        return $this->subirActivo($request, BrandingAssetType::LogoCompact);
    }

    public function deleteLogoCompact(): JsonResponse
    {
        return $this->eliminarActivo(BrandingAssetType::LogoCompact);
    }

    public function uploadLogoDark(UploadCompanyLogoRequest $request): JsonResponse
    {
        return $this->subirActivo($request, BrandingAssetType::LogoDark);
    }

    public function deleteLogoDark(): JsonResponse
    {
        return $this->eliminarActivo(BrandingAssetType::LogoDark);
    }

    public function uploadFavicon(UploadCompanyFaviconRequest $request): JsonResponse
    {
        return $this->subirActivo($request, BrandingAssetType::Favicon);
    }

    public function deleteFavicon(): JsonResponse
    {
        return $this->eliminarActivo(BrandingAssetType::Favicon);
    }

    private function subirActivo(UploadCompanyLogoRequest|UploadCompanyFaviconRequest $request, BrandingAssetType $type): JsonResponse
    {
        $branding = $this->brandingActual();
        $this->authorize('update', $branding);

        $branding = $this->brandingService->updateAsset($branding, $type, $request->file('file'), $request->user());

        return ApiResponse::success($this->brandingService->present($branding));
    }

    private function eliminarActivo(BrandingAssetType $type): JsonResponse
    {
        $branding = $this->brandingActual();
        $this->authorize('update', $branding);

        $branding = $this->brandingService->removeAsset($branding, $type, request()->user());

        return ApiResponse::success($this->brandingService->present($branding));
    }

    private function brandingActual(): CompanyBranding
    {
        return $this->brandingService->getOrCreate($this->empresaActual());
    }

    private function empresaActual(): Company
    {
        $empresa = $this->context->empresa();

        abort_if($empresa === null, 409, 'Selecciona una empresa activa (X-Empresa-Id) antes de gestionar su configuración.');

        return $empresa;
    }
}
