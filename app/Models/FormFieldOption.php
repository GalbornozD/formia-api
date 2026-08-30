<?php

namespace App\Models;

use App\Policies\FormFieldOptionPolicy;
use Database\Factories\FormFieldOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'form_field_id',
    'option_value',
    'option_label',
    'sort_order',
    'is_default',
    'is_active',
    'settings',
])]
#[UsePolicy(FormFieldOptionPolicy::class)]
class FormFieldOption extends Model
{
    /** @use HasFactory<FormFieldOptionFactory> */
    use HasFactory;

    public $table = 'form_field_options';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'form_field_id' => 'integer',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
