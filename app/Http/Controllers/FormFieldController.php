<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormBuilder\DuplicateFormFieldRequest;
use App\Http\Requests\FormBuilder\ReorderFormFieldsRequest;
use App\Http\Requests\FormBuilder\StoreFormFieldRequest;
use App\Http\Requests\FormBuilder\UpdateFormFieldRequest;
use App\Models\Company;
use App\Models\FormField;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Services\FormDefinitionService;
use App\Services\FormFieldService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldController extends Controller
{
    public function __construct(
        private readonly FormFieldService $fieldService,
        private readonly FormDefinitionService $definitionService,
    ) {}

    public function index(Company $empresa, FormType $formType, FormTypeVersion $version): JsonResponse
    {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('viewAny', [FormField::class, $version]);

        return ApiResponse::success($this->definitionService->fields($version));
    }

    public function store(
        StoreFormFieldRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('create', [FormField::class, $version]);
        $field = $this->fieldService->create($version, $request->validated(), $request->user());

        return ApiResponse::success($this->definitionService->field($field), 201);
    }

    public function show(
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field);
        $this->authorize('view', $field);

        return ApiResponse::success($this->definitionService->field($field));
    }

    public function update(
        UpdateFormFieldRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field);
        $this->authorize('update', $field);
        $field = $this->fieldService->update($field, $request->validated(), $request->user());

        return ApiResponse::success($this->definitionService->field($field));
    }

    public function destroy(
        Request $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field);
        $this->authorize('delete', $field);
        $this->fieldService->delete($field, $request->user());

        return ApiResponse::success();
    }

    public function duplicate(
        DuplicateFormFieldRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field);
        $this->authorize('update', $field);
        $duplicate = $this->fieldService->duplicate($field, $request->validated(), $request->user());

        return ApiResponse::success($this->definitionService->field($duplicate), 201);
    }

    public function reorder(
        ReorderFormFieldsRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version);
        $this->authorize('update', $version);
        $this->fieldService->reorder($version, $request->validated('fields'), $request->user());

        return ApiResponse::success($this->definitionService->fields($version));
    }

    private function assertHierarchy(
        Company $company,
        FormType $formType,
        FormTypeVersion $version,
        ?FormField $field = null,
    ): void {
        abort_unless($formType->company_id === $company->id, 404);
        abort_unless($version->form_type_id === $formType->id, 404);

        if ($field !== null) {
            abort_unless($field->form_type_version_id === $version->id, 404);
        }
    }
}
