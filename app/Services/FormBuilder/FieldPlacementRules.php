<?php

namespace App\Services\FormBuilder;

use App\Models\FieldType;
use Illuminate\Validation\ValidationException;

final class FieldPlacementRules
{
    /** @var list<string> */
    private const TABLE_COLUMN_TYPE_CODES = [
        'text',
        'number',
        'currency',
        'percentage',
        'email',
        'phone',
        'url',
        'rut',
        'date',
        'time',
        'datetime',
        'select',
        'yes_no',
    ];

    public function ensureCanBeChildOf(
        FieldType $parentType,
        FieldType $childType,
        string $errorKey,
    ): void {
        if ($parentType->code !== 'table' || $this->canBeTableColumn((string) $childType->code)) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => "El tipo de campo '{$childType->name}' no puede usarse como columna de una tabla.",
        ]);
    }

    public function canBeTableColumn(string $fieldTypeCode): bool
    {
        return in_array($fieldTypeCode, self::TABLE_COLUMN_TYPE_CODES, true);
    }
}
