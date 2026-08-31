<?php

namespace App\Http\Resources\FormResponses;

use App\Models\FormResponse;
use App\Services\FormResponses\FormPublicationAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyFormPublicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = app(FormPublicationAccessService::class)->availabilityForUser($this->resource, $user);
        $latestResponse = FormResponse::query()
            ->where('form_publication_id', $this->id)
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->first();
        $assignment = $this->assignments->first();

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'form_type_name' => $this->formType?->name,
            'version' => $this->version?->version,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'availability_status' => $status->value,
            'response_uuid' => $latestResponse?->id,
            'response_status' => $latestResponse?->status?->value,
            'assigned_at' => $assignment?->assigned_at?->toIso8601String(),
            'submitted_at' => $assignment?->submitted_at?->toIso8601String() ?? $latestResponse?->submitted_at?->toIso8601String(),
            'show_progress' => $this->show_progress,
            'show_question_numbers' => $this->show_question_numbers,
        ];
    }
}
