<?php

namespace App\Http\Resources\FormResponses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'channel' => $this->channel->value,
            'recipient' => $this->recipient,
            'status' => $this->status->value,
            'guest_respondent' => $this->whenLoaded('guestRespondent', fn () => $this->guestRespondent === null ? null : [
                'id' => $this->guestRespondent->id,
                'name' => $this->guestRespondent->name,
                'email' => $this->guestRespondent->email,
            ]),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Solo presente justo tras crear/regenerar (atributo dinámico
            // seteado por el controller) — nunca reconstruible desde BD.
            'url' => $this->when($this->url !== null, fn () => $this->url),
        ];
    }
}
