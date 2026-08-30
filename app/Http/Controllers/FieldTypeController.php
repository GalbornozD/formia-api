<?php

namespace App\Http\Controllers;

use App\Models\FieldType;
use App\Models\User;
use App\Services\FormDefinitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldTypeController extends Controller
{
    public function __construct(private readonly FormDefinitionService $definitionService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->esMaster() || $user->esAdministrador(), 403);

        $fieldTypes = FieldType::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (FieldType $fieldType): array => $this->definitionService->fieldType($fieldType));

        return ApiResponse::success($fieldTypes);
    }
}
