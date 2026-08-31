<?php

namespace App\Http\Controllers\FormResponses;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormResponses\SaveFormResponseRequest;
use App\Http\Requests\FormResponses\StartFormResponseRequest;
use App\Http\Resources\FormResponses\FormResponseResource;
use App\Services\FormResponses\FormPublicationAccessService;
use App\Services\FormResponses\FormResponseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MyFormResponseController extends Controller
{
    public function __construct(
        private readonly FormPublicationAccessService $accessService,
        private readonly FormResponseService $responseService,
    ) {}

    public function store(StartFormResponseRequest $request, string $publicationUuid): JsonResponse
    {
        $publication = $this->accessService->findForUser($publicationUuid, $request->user());
        $response = $this->responseService->startForUser($publication, $request->user(), $request->validated());

        return ApiResponse::success((new FormResponseResource($response))->resolve(), 201);
    }

    public function show(StartFormResponseRequest $request, string $responseUuid): JsonResponse
    {
        $response = $this->accessService->responseForUser($responseUuid, $request->user());
        $this->authorize('view', $response);

        return ApiResponse::success((new FormResponseResource($response))->resolve());
    }

    public function update(SaveFormResponseRequest $request, string $responseUuid): JsonResponse
    {
        $response = $this->accessService->responseForUser($responseUuid, $request->user());
        $this->authorize('update', $response);

        $response = $this->responseService->save($response, $request->validated('answers'), false);

        return ApiResponse::success((new FormResponseResource($response))->resolve());
    }

    public function submit(SaveFormResponseRequest $request, string $responseUuid): JsonResponse
    {
        $response = $this->accessService->responseForUser($responseUuid, $request->user());
        $this->authorize('update', $response);

        $response = $this->responseService->save($response, $request->validated('answers'), true);

        return ApiResponse::success((new FormResponseResource($response))->resolve());
    }
}
