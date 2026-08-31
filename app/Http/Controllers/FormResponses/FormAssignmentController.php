<?php

namespace App\Http\Controllers\FormResponses;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormResponses\StoreFormAssignmentRequest;
use App\Http\Resources\FormResponses\FormAssignmentResource;
use App\Models\Company;
use App\Models\FormAssignment;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Services\FormResponses\FormPublicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormAssignmentController extends Controller
{
    public function __construct(private readonly FormPublicationService $publicationService) {}

    public function index(Request $request, Company $empresa, FormType $formType, FormPublication $publication): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $publication);
        $this->authorize('viewAny', [FormAssignment::class, $publication]);

        $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(AssignmentStatus::values())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $assignments = $publication->assignments()
            ->with(['user', 'guestRespondent'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('assigned_at')
            ->paginate(perPage: (int) $request->input('per_page', 20))
            ->through(fn (FormAssignment $assignment) => (new FormAssignmentResource($assignment))->resolve());

        return ApiResponse::success($assignments);
    }

    public function store(
        StoreFormAssignmentRequest $request,
        Company $empresa,
        FormType $formType,
        FormPublication $publication,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $publication);

        $this->authorize('create', [FormAssignment::class, $publication]);

        $assignments = $this->publicationService->assign($publication, $request->validated('user_ids'), $request->user());

        return ApiResponse::success(
            FormAssignmentResource::collection($assignments->load(['user', 'publication']))->resolve(),
            201,
        );
    }

    private function assertHierarchy(Company $empresa, FormType $formType, FormPublication $publication): void
    {
        abort_unless($formType->company_id === $empresa->id, 404);
        abort_unless($publication->company_id === $empresa->id && $publication->form_type_id === $formType->id, 404);
    }
}
