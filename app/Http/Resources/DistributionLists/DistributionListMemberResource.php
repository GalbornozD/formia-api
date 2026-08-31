<?php

namespace App\Http\Resources\DistributionLists;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionListMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_type' => $this->member_type->value,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'uuid' => $this->user->uuid,
                'email' => $this->user->email,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ]),
            'guest_respondent' => $this->whenLoaded('guestRespondent', fn () => $this->guestRespondent === null ? null : [
                'id' => $this->guestRespondent->id,
                'name' => $this->guestRespondent->name,
                'email' => $this->guestRespondent->email,
                'phone' => $this->guestRespondent->phone,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
