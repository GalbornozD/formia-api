<?php

namespace App\Services\FormResponses;

use App\Enums\RespondentType;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FormAssignment;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormPublicationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, FormType $formType, array $data, User $actor): FormPublication
    {
        return DB::transaction(function () use ($company, $formType, $data, $actor): FormPublication {
            abort_unless($formType->company_id === $company->id, 404);

            $version = $this->validatedVersion($formType, (int) $data['form_type_version_id']);
            $slug = $this->uniqueSlug($company->id, (string) ($data['slug'] ?? $data['name']));

            $publication = FormPublication::query()->create([
                ...$this->dataForSave($data, $slug),
                'company_id' => $company->id,
                'form_type_id' => $formType->id,
                'form_type_version_id' => $version->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $publication->load(['formType', 'version']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FormPublication $publication, FormType $formType, array $data, User $actor): FormPublication
    {
        return DB::transaction(function () use ($publication, $formType, $data, $actor): FormPublication {
            $publication = FormPublication::query()->lockForUpdate()->findOrFail($publication->id);
            abort_unless($publication->form_type_id === $formType->id, 404);

            $slug = array_key_exists('slug', $data) || array_key_exists('name', $data)
                ? $this->uniqueSlug($publication->company_id, (string) ($data['slug'] ?? $data['name'] ?? $publication->name), $publication->id)
                : $publication->slug;

            $versionId = (int) ($data['form_type_version_id'] ?? $publication->form_type_version_id);
            $version = $this->validatedVersion($formType, $versionId);

            $publication->forceFill([
                ...$this->dataForSave($data, $slug, $publication),
                'form_type_version_id' => $version->id,
                'updated_by' => $actor->id,
            ])->save();

            return $publication->refresh()->load(['formType', 'version']);
        });
    }

    /**
     * @param  list<int>  $userIds
     * @return EloquentCollection<int, FormAssignment>
     */
    public function assign(FormPublication $publication, array $userIds, User $actor): EloquentCollection
    {
        return DB::transaction(function () use ($publication, $userIds, $actor): EloquentCollection {
            $publication = FormPublication::query()->lockForUpdate()->findOrFail($publication->id);
            $validUserIds = CompanyUser::query()
                ->where('company_id', $publication->company_id)
                ->where('status', true)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->all();

            if (count($validUserIds) !== count($userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Todos los usuarios asignados deben pertenecer activos a la empresa.',
                ]);
            }

            foreach ($validUserIds as $userId) {
                FormAssignment::query()->firstOrCreate(
                    ['form_publication_id' => $publication->id, 'user_id' => $userId],
                    [
                        'company_id' => $publication->company_id,
                        'respondent_type' => RespondentType::User,
                        'assigned_at' => now(),
                        'created_by' => $actor->id,
                    ],
                );
            }

            return $publication->assignments()
                ->with('user')
                ->whereIn('user_id', $validUserIds)
                ->orderBy('assigned_at')
                ->get();
        });
    }

    private function validatedVersion(FormType $formType, int $versionId): FormTypeVersion
    {
        $version = FormTypeVersion::query()->whereKey($versionId)->firstOrFail();

        if ($version->form_type_id !== $formType->id || ! $version->is_published || ! $version->is_active) {
            throw ValidationException::withMessages([
                'form_type_version_id' => 'Solo se puede publicar una version activa y publicada del formulario.',
            ]);
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dataForSave(array $data, string $slug, ?FormPublication $publication = null): array
    {
        $respondentType = RespondentType::from((string) ($data['respondent_type'] ?? $publication?->respondent_type->value ?? RespondentType::Anonymous->value));

        return [
            'name' => (string) ($data['name'] ?? $publication?->name),
            'slug' => $slug,
            'respondent_type' => $respondentType,
            'starts_at' => $data['starts_at'] ?? $publication?->starts_at,
            'ends_at' => $data['ends_at'] ?? $publication?->ends_at,
            'allow_draft' => $data['allow_draft'] ?? $publication?->allow_draft ?? true,
            'allow_edit_after_submit' => $data['allow_edit_after_submit'] ?? $publication?->allow_edit_after_submit ?? false,
            'show_progress' => $data['show_progress'] ?? $publication?->show_progress ?? true,
            'show_question_numbers' => $data['show_question_numbers'] ?? $publication?->show_question_numbers ?? true,
            'max_responses_per_respondent' => $data['max_responses_per_respondent'] ?? $publication?->max_responses_per_respondent,
            'thank_you_title' => $data['thank_you_title'] ?? $publication?->thank_you_title,
            'thank_you_description' => $data['thank_you_description'] ?? $publication?->thank_you_description,
            'is_active' => $data['is_active'] ?? $publication?->is_active ?? true,
        ];
    }

    private function uniqueSlug(int $companyId, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $base = $base === '' ? Str::uuid()->toString() : mb_substr($base, 0, 150);
        $candidate = $base;
        $suffix = 2;

        while (FormPublication::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = mb_substr($base, 0, 150 - strlen((string) $suffix) - 1).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
