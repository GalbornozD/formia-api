<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class FormTypeService
{
    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function create(Company $company, array $data, User $actor): FormType
    {
        return DB::transaction(function () use ($company, $data, $actor): FormType {
            $formType = new FormType;
            $formType->forceFill([
                'company_id' => $company->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $version = new FormTypeVersion;
            $version->forceFill([
                'form_type_id' => $formType->id,
                'version' => 1,
                'is_published' => false,
                'is_active' => true,
                'published_at' => null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            return $formType->load('latestVersion');
        });
    }

    /**
     * @param  array{name?: string, description?: string|null, status?: bool}  $data
     */
    public function update(FormType $formType, array $data, User $actor): FormType
    {
        $formType->forceFill([...$data, 'updated_by' => $actor->id])->save();

        return $formType->refresh();
    }

    public function deactivate(FormType $formType, User $actor): FormType
    {
        return $this->update($formType, ['status' => false], $actor);
    }
}
