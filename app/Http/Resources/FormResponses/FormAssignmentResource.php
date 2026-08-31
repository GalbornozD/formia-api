<?php

namespace App\Http\Resources\FormResponses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'publication_uuid' => $this->whenLoaded('publication', fn () => $this->publication->uuid),
            'respondent_type' => $this->respondent_type->value,
            'status' => $this->status->value,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'name' => trim($this->user->first_name.' '.$this->user->last_name),
            ]),
            'guest_respondent' => $this->whenLoaded('guestRespondent', fn () => $this->guestRespondent === null ? null : [
                'id' => $this->guestRespondent->id,
                'name' => $this->guestRespondent->name,
                'email' => $this->guestRespondent->email,
                'phone' => $this->guestRespondent->phone,
            ]),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
