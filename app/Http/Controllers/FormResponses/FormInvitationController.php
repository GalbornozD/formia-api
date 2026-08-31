<?php

namespace App\Http\Controllers\FormResponses;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResponses\FormInvitationResource;
use App\Models\Company;
use App\Models\FormInvitation;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Services\FormResponses\InvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormInvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitationService) {}

    public function index(Request $request, Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        $this->authorize('viewAny', $publication);

        $invitations = $publication->invitations()
            ->with('guestRespondent')
            ->orderByDesc('created_at')
            ->paginate(perPage: min(100, max(1, (int) $request->input('per_page', 20))))
            ->through(fn (FormInvitation $invitation) => (new FormInvitationResource($invitation))->resolve());

        return ApiResponse::success($invitations);
    }

    public function regenerate(Company $empresa, FormType $formType, FormPublication $publication, FormInvitation $invitation): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        abort_unless($invitation->form_publication_id === $publication->id, 404);
        $this->authorize('update', $invitation);

        $result = $this->invitationService->regenerate($invitation, request()->user());
        $result['invitation']->setAttribute('url', $result['url']);

        return ApiResponse::success((new FormInvitationResource($result['invitation']))->resolve());
    }

    public function cancel(Company $empresa, FormType $formType, FormPublication $publication, FormInvitation $invitation): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        abort_unless($invitation->form_publication_id === $publication->id, 404);
        $this->authorize('update', $invitation);

        $this->invitationService->cancel($invitation, request()->user());

        return ApiResponse::success((new FormInvitationResource($invitation))->resolve());
    }

    private function assertHierarchy(Company $empresa, FormType $formType, FormPublication $publication): void
    {
        abort_unless($formType->company_id === $empresa->id, 404);
        abort_unless($publication->company_id === $empresa->id && $publication->form_type_id === $formType->id, 404);
    }
}
