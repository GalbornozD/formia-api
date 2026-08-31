<?php

namespace App\Http\Controllers\FormResponses;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormResponses\StorePublicationAudienceRequest;
use App\Http\Resources\FormResponses\FormPublicationAudienceResource;
use App\Models\Company;
use App\Models\FormAssignment;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Services\FormResponses\PublicationAudienceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicationAudienceController extends Controller
{
    public function __construct(private readonly PublicationAudienceService $audienceService) {}

    public function show(Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        $this->authorize('viewAny', [FormAssignment::class, $publication]);

        $audience = $publication->currentAudience()->with(['sources.distributionList', 'sources.user', 'sources.guestRespondent'])->first();

        return ApiResponse::success($audience === null ? null : (new FormPublicationAudienceResource($audience))->resolve());
    }

    public function store(StorePublicationAudienceRequest $request, Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        $this->authorize('create', [FormAssignment::class, $publication]);

        $audience = $this->audienceService->publish($publication, $request->validated(), $request->user());

        return ApiResponse::success((new FormPublicationAudienceResource($audience->load(['sources.distributionList', 'sources.user', 'sources.guestRespondent'])))->resolve(), 201);
    }

    public function sync(Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        $this->authorize('create', [FormAssignment::class, $publication]);

        $audience = $this->audienceService->syncNow($publication, request()->user());

        return ApiResponse::success((new FormPublicationAudienceResource($audience->load(['sources.distributionList', 'sources.user', 'sources.guestRespondent'])))->resolve());
    }

    public function preview(StorePublicationAudienceRequest $request, Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        $this->authorize('create', [FormAssignment::class, $publication]);

        return ApiResponse::success($this->audienceService->preview($publication, $request->validated()));
    }

    private function assertHierarchy(Company $empresa, FormType $formType, FormPublication $publication): void
    {
        abort_unless($formType->company_id === $empresa->id, 404);
        abort_unless($publication->company_id === $empresa->id && $publication->form_type_id === $formType->id, 404);
    }
}
