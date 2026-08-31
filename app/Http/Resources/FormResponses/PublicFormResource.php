<?php

namespace App\Http\Resources\FormResponses;

use App\Enums\FormAvailabilityStatus;
use App\Services\FormResponses\ResponseFormDefinitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicFormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->resource->getAttribute('availability_status');

        return app(ResponseFormDefinitionService::class)->publicForm(
            $this->resource,
            $status instanceof FormAvailabilityStatus ? $status : FormAvailabilityStatus::Pending,
        );
    }
}
