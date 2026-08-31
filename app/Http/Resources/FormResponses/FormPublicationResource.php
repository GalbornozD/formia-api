<?php

namespace App\Http\Resources\FormResponses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormPublicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'form_type_id' => $this->form_type_id,
            'form_type_version_id' => $this->form_type_version_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'public_path' => '/f/'.$this->uuid,
            'respondent_type' => $this->respondent_type->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'allow_draft' => $this->allow_draft,
            'allow_edit_after_submit' => $this->allow_edit_after_submit,
            'show_progress' => $this->show_progress,
            'show_question_numbers' => $this->show_question_numbers,
            'max_responses_per_respondent' => $this->max_responses_per_respondent,
            'thank_you_title' => $this->thank_you_title,
            'thank_you_description' => $this->thank_you_description,
            'is_active' => $this->is_active,
            'form_type' => $this->whenLoaded('formType', fn () => [
                'id' => $this->formType->id,
                'name' => $this->formType->name,
            ]),
            'version' => $this->whenLoaded('version', fn () => [
                'id' => $this->version->id,
                'version' => $this->version->version,
                'published_at' => $this->version->published_at?->toIso8601String(),
            ]),
            'responses_count' => $this->whenCounted('responses'),
            'assignments_count' => $this->whenCounted('assignments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
