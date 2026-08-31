<?php

namespace App\Http\Controllers\FormResponses;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResponses\MyFormPublicationResource;
use App\Http\Resources\FormResponses\PublicFormResource;
use App\Services\FormResponses\FormPublicationAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyFormController extends Controller
{
    public function __construct(private readonly FormPublicationAccessService $accessService) {}

    public function index(Request $request): JsonResponse
    {
        $publications = $this->accessService->publicationsForUser($request->user());

        return ApiResponse::success(MyFormPublicationResource::collection($publications)->resolve());
    }

    public function show(Request $request, string $publicationUuid): JsonResponse
    {
        $publication = $this->accessService->findForUser($publicationUuid, $request->user());
        $publication->setAttribute('availability_status', $this->accessService->availabilityForUser($publication, $request->user()));

        return ApiResponse::success((new PublicFormResource($publication))->resolve());
    }
}
