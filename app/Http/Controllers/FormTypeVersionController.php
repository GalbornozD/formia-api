<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormBuilder\CloneFormVersionRequest;
use App\Http\Requests\FormBuilder\SaveFormDefinitionRequest;
use App\Models\Company;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Services\FormDefinitionSaveService;
use App\Services\FormDefinitionService;
use App\Services\FormVersionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormTypeVersionController extends Controller
{
    public function __construct(
        private readonly FormVersionService $versionService,
        private readonly FormDefinitionService $definitionService,
        private readonly FormDefinitionSaveService $definitionSaveService,
    ) {}

    public function index(Company $empresa, FormType $formType): JsonResponse
    {
        $this->assertFormTypeCompany($empresa, $formType);
        $this->authorize('viewAny', [FormTypeVersion::class, $formType]);

        $versions = $formType->versions()
            ->orderBy('version')
            ->get()
            ->map(fn (FormTypeVersion $version): array => $this->definitionService->version($version));

        return ApiResponse::success($versions);
    }

    public function show(Company $empresa, FormType $formType, FormTypeVersion $version): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('view', $version);

        return ApiResponse::success($this->definitionService->version($version));
    }

    public function store(CloneFormVersionRequest $request, Company $empresa, FormType $formType): JsonResponse
    {
        $this->assertFormTypeCompany($empresa, $formType);
        $this->authorize('create', [FormTypeVersion::class, $formType]);
        $version = $this->versionService->clone(
            $formType,
            $request->validated('source_version_id'),
            $request->user(),
        );

        return ApiResponse::success($this->definitionService->version($version), 201);
    }

    public function publish(Request $request, Company $empresa, FormType $formType, FormTypeVersion $version): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('publish', $version);
        $version = $this->versionService->publish($formType, $version, $request->user());

        return ApiResponse::success($this->definitionService->version($version));
    }

    public function builder(Company $empresa, FormType $formType, FormTypeVersion $version): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('view', $version);

        return ApiResponse::success($this->definitionService->builder($version));
    }

    public function saveBuilder(
        SaveFormDefinitionRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('update', $version);
        $version = $this->definitionSaveService->save(
            $version,
            $request->validated('fields'),
            $request->user(),
        );

        return ApiResponse::success($this->definitionService->builder($version));
    }

    private function assertHierarchy(Company $company, FormType $formType, FormTypeVersion $version): void
    {
        $this->assertFormTypeCompany($company, $formType);
        abort_unless($version->form_type_id === $formType->id, 404);
    }

    private function assertFormTypeCompany(Company $company, FormType $formType): void
    {
        abort_unless($formType->company_id === $company->id, 404);
    }
}
