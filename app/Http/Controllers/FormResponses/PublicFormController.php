<?php

namespace App\Http\Controllers\FormResponses;

use App\Enums\FormAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FormResponses\PublicFormResource;
use App\Services\FormResponses\FormPublicationAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicFormController extends Controller
{
    public function __construct(private readonly FormPublicationAccessService $accessService) {}

    public function show(string $publicationUuid): JsonResponse
    {
        $publication = $this->accessService->findForGuest($publicationUuid);
        $status = $publication->ends_at !== null && $publication->ends_at->isPast()
            ? FormAvailabilityStatus::Expired
            : FormAvailabilityStatus::Pending;
        $publication->setAttribute('availability_status', $status);

        return ApiResponse::success((new PublicFormResource($publication))->resolve());
    }
}
