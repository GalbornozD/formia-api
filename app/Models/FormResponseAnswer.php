<?php

namespace App\Models;

use Database\Factories\FormResponseAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form_response_id', 'form_field_id', 'value_json'])]
class FormResponseAnswer extends Model
{
    /** @use HasFactory<FormResponseAnswerFactory> */
    use HasFactory;

    public $table = 'form_response_answers';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FormResponse, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }
}
