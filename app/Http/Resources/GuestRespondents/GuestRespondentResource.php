<?php

namespace App\Http\Resources\GuestRespondents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestRespondentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsapp_phone,
            'external_reference' => $this->external_reference,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
