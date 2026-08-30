<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormBuilder\ReorderFormFieldOptionsRequest;
use App\Http\Requests\FormBuilder\StoreFormFieldOptionRequest;
use App\Http\Requests\FormBuilder\UpdateFormFieldOptionRequest;
use App\Models\Company;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Services\FormDefinitionService;
use App\Services\FormFieldOptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldOptionController extends Controller
{
    public function __construct(
        private readonly FormFieldOptionService $optionService,
        private readonly FormDefinitionService $definitionService,
    ) {}

    public function store(
        StoreFormFieldOptionRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field);
        $this->authorize('create', [FormFieldOption::class, $field]);
        $option = $this->optionService->create($field, $request->validated(), $request->user());

        return ApiResponse::success($this->definitionService->option($option), 201);
    }

    public function update(
        UpdateFormFieldOptionRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
        FormFieldOption $option,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field, $option);
        $this->authorize('update', $option);
        $option = $this->optionService->update($option, $request->validated(), $request->user());

        return ApiResponse::success($this->definitionService->option($option));
    }

    public function destroy(
        Request $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
        FormFieldOption $option,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field, $option);
        $this->authorize('delete', $option);
        $this->optionService->delete($option, $request->user());

        return ApiResponse::success();
    }

    public function reorder(
        ReorderFormFieldOptionsRequest $request,
        Company $empresa,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
    ): JsonResponse {
        $this->assertHierarchy($empresa, $formType, $version, $field);
        $this->authorize('update', $field);
        $this->optionService->reorder($field, $request->validated('options'), $request->user());

        return ApiResponse::success(
            $field->options()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (FormFieldOption $option): array => $this->definitionService->option($option)),
        );
    }

    private function assertHierarchy(
        Company $company,
        FormType $formType,
        FormTypeVersion $version,
        FormField $field,
        ?FormFieldOption $option = null,
    ): void {
        abort_unless($formType->company_id === $company->id, 404);
        abort_unless($version->form_type_id === $formType->id, 404);
        abort_unless($field->form_type_version_id === $version->id, 404);

        if ($option !== null) {
            abort_unless($option->form_field_id === $field->id, 404);
        }
    }
}
