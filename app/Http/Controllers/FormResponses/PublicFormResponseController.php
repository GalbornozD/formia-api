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
use Illuminate\Http\Request;

class PublicFormResponseController extends Controller
{
    public function __construct(
        private readonly FormPublicationAccessService $accessService,
        private readonly FormResponseService $responseService,
    ) {}

    public function store(StartFormResponseRequest $request, string $publicationUuid): JsonResponse
    {
        $publication = $this->accessService->findForGuest($publicationUuid);
        $result = $this->responseService->startForGuest($publication, $request->validated());
        $data = (new FormResponseResource($result['response']))->resolve();
        $data['access_token'] = $result['token'];

        return ApiResponse::success($data, 201);
    }

    public function show(Request $request, string $responseUuid): JsonResponse
    {
        $response = $this->accessService->responseForGuest($responseUuid, $request->header('X-Response-Token'));

        return ApiResponse::success((new FormResponseResource($response))->resolve());
    }

    public function update(SaveFormResponseRequest $request, string $responseUuid): JsonResponse
    {
        $response = $this->accessService->responseForGuest($responseUuid, $request->header('X-Response-Token'));
        $response = $this->responseService->save($response, $request->validated('answers'), false);

        return ApiResponse::success((new FormResponseResource($response))->resolve());
    }

    public function submit(SaveFormResponseRequest $request, string $responseUuid): JsonResponse
    {
        $response = $this->accessService->responseForGuest($responseUuid, $request->header('X-Response-Token'));
        $response = $this->responseService->save($response, $request->validated('answers'), true);

        return ApiResponse::success((new FormResponseResource($response))->resolve());
    }
}
