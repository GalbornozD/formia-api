<?php

namespace App\Http\Resources\FormResponses;

use App\Enums\FormResponseStatus;
use App\Enums\RespondentType;
use App\Services\FormResponses\ResponseFormDefinitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->id,
            'publication_uuid' => $this->publication?->uuid,
            'status' => $this->status->value,
            'respondent_type' => $this->respondent_type->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'last_saved_at' => $this->last_saved_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'can_edit' => $this->status !== FormResponseStatus::Submitted || (bool) $this->publication?->allow_edit_after_submit,
            'respondent' => $this->respondent(),
            'answers' => $this->answers
                ->map(fn ($answer): array => [
                    'field_key' => $answer->field?->field_key,
                    'value' => $answer->value_json,
                ])
                ->filter(fn (array $answer): bool => $answer['field_key'] !== null)
                ->values()
                ->all(),
            'form' => $this->publication !== null
                ? app(ResponseFormDefinitionService::class)->publicForm($this->publication)
                : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function respondent(): ?array
    {
        if ($this->respondent_type === RespondentType::User && $this->user !== null) {
            return [
                'name' => trim($this->user->first_name.' '.$this->user->last_name),
                'email' => $this->user->email,
            ];
        }

        if ($this->respondent_type === RespondentType::Guest && $this->guestRespondent !== null) {
            return [
                'name' => $this->guestRespondent->name,
                'email' => $this->guestRespondent->email,
                'phone' => $this->guestRespondent->phone,
            ];
        }

        return null;
    }
}
