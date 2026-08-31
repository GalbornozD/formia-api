<?php

namespace App\Http\Requests\DistributionLists;

use App\Enums\DistributionMemberType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddDistributionListMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_type' => ['required', Rule::in(DistributionMemberType::values())],
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.0' => ['required'],
        ];
    }
}
