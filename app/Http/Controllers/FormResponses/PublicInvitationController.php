<?php

namespace App\Http\Controllers\FormResponses;

use App\Enums\FormAvailabilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormResponses\StartFormResponseRequest;
use App\Http\Resources\FormResponses\FormResponseResource;
use App\Http\Resources\FormResponses\PublicFormResource;
use App\Services\FormResponses\FormPublicationAccessService;
use App\Services\FormResponses\FormResponseService;
use App\Services\FormResponses\InvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Entrada pública para invitados con link personalizado
 * (`/f/{uuid}/invite/{token}`). Nunca confía en IDs internos: todo se
 * resuelve desde el hash del token vía FormPublicationAccessService::resolveInvitation.
 */
class PublicInvitationController extends Controller
{
    public function __construct(
        private readonly FormPublicationAccessService $accessService,
        private readonly FormResponseService $responseService,
        private readonly InvitationService $invitationService,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invitation = $this->accessService->resolveInvitation($token);
        $this->invitationService->markOpened($invitation);

        $publication = $invitation->publication;
        $status = $publication->ends_at !== null && $publication->ends_at->isPast()
            ? FormAvailabilityStatus::Expired
            : FormAvailabilityStatus::Pending;
        $publication->setAttribute('availability_status', $status);

        return ApiResponse::success((new PublicFormResource($publication))->resolve());
    }

    public function store(StartFormResponseRequest $request, string $token): JsonResponse
    {
        $invitation = $this->accessService->resolveInvitation($token);

        $assignment = $invitation->assignment;
        $guest = $invitation->guestRespondent;
        abort_if($assignment === null || $guest === null, 404);

        $result = $this->responseService->startForInvitedGuest($invitation, $assignment, $guest, $request->validated());
        $data = (new FormResponseResource($result['response']))->resolve();
        $data['access_token'] = $result['token'];

        return ApiResponse::success($data, 201);
    }
}
