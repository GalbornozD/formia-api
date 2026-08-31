<?php

namespace App\Http\Controllers\FormResponses;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormResponses\StoreFormPublicationRequest;
use App\Http\Requests\FormResponses\UpdateFormPublicationRequest;
use App\Http\Resources\FormResponses\FormPublicationResource;
use App\Models\Company;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Services\FormResponses\FormPublicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminFormPublicationController extends Controller
{
    public function __construct(private readonly FormPublicationService $publicationService) {}

    public function index(Company $empresa, FormType $formType): JsonResponse
    {
        $this->assertFormTypeCompany($empresa, $formType);
        $this->authorize('viewAny', [FormPublication::class, $formType]);

        $publications = FormPublication::query()
            ->where('company_id', $empresa->id)
            ->where('form_type_id', $formType->id)
            ->with(['formType', 'version'])
            ->withCount(['assignments', 'responses'])
            ->latest('created_at')
            ->get();

        return ApiResponse::success(FormPublicationResource::collection($publications)->resolve());
    }

    public function store(StoreFormPublicationRequest $request, Company $empresa, FormType $formType): JsonResponse
    {
        $this->assertFormTypeCompany($empresa, $formType);
        $this->authorize('create', [FormPublication::class, $formType]);

        $publication = $this->publicationService->create($empresa, $formType, $request->validated(), $request->user());

        return ApiResponse::success((new FormPublicationResource($publication))->resolve(), 201);
    }

    public function show(Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertPublicationHierarchy($empresa, $formType, $publication);
        $this->authorize('view', $publication);

        $publication->load(['formType', 'version'])->loadCount(['assignments', 'responses']);

        return ApiResponse::success((new FormPublicationResource($publication))->resolve());
    }

    public function update(
        UpdateFormPublicationRequest $request,
        Company $empresa,
        FormType $formType,
        FormPublication $publication,
    ): JsonResponse {
        $this->assertPublicationHierarchy($empresa, $formType, $publication);
        $this->authorize('update', $publication);

        $publication = $this->publicationService->update($publication, $formType, $request->validated(), $request->user());

        return ApiResponse::success((new FormPublicationResource($publication))->resolve());
    }

    private function assertFormTypeCompany(Company $company, FormType $formType): void
    {
        abort_unless($formType->company_id === $company->id, 404);
    }

    private function assertPublicationHierarchy(Company $company, FormType $formType, FormPublication $publication): void
    {
        $this->assertFormTypeCompany($company, $formType);
        abort_unless($publication->company_id === $company->id && $publication->form_type_id === $formType->id, 404);
    }
}
