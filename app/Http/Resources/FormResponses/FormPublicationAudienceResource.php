<?php

namespace App\Http\Resources\FormResponses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormPublicationAudienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'respondent_type' => $this->respondent_type->value,
            'recipients_count' => $this->recipients_count,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'sources' => $this->whenLoaded('sources', fn () => $this->sources->map(fn ($source) => [
                'source_type' => $source->source_type->value,
                'distribution_list' => $source->relationLoaded('distributionList') && $source->distributionList !== null ? [
                    'id' => $source->distributionList->id,
                    'name' => $source->distributionList->name,
                ] : null,
                'user' => $source->relationLoaded('user') && $source->user !== null ? [
                    'id' => $source->user->id,
                    'email' => $source->user->email,
                ] : null,
                'guest_respondent' => $source->relationLoaded('guestRespondent') && $source->guestRespondent !== null ? [
                    'id' => $source->guestRespondent->id,
                    'name' => $source->guestRespondent->name,
                ] : null,
            ])),
            // Solo presente inmediatamente después de publish()/syncNow():
            // nunca se reconstruye desde BD (no se guarda el token en texto plano).
            'new_invitations' => $this->when(
                is_array($this->new_invitations),
                fn () => collect($this->new_invitations)->map(fn ($item) => [
                    'guest_respondent_id' => $item['invitation']->guest_respondent_id,
                    'url' => $item['url'],
                ]),
            ),
        ];
    }
}
