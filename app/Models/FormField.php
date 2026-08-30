<?php

namespace App\Models;

use App\Policies\FormFieldPolicy;
use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'form_type_version_id',
    'field_type_id',
    'parent_field_id',
    'field_key',
    'label',
    'description',
    'placeholder',
    'default_value',
    'is_required',
    'is_readonly',
    'is_hidden',
    'is_active',
    'sort_order',
    'width',
    'validation_rules',
    'settings',
])]
#[UsePolicy(FormFieldPolicy::class)]
class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    public $table = 'form_fields';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'form_type_version_id' => 'integer',
            'field_type_id' => 'integer',
            'parent_field_id' => 'integer',
            'default_value' => 'array',
            'is_required' => 'boolean',
            'is_readonly' => 'boolean',
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'validation_rules' => 'array',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FormTypeVersion, $this>
     */
    public function formTypeVersion(): BelongsTo
    {
        return $this->belongsTo(FormTypeVersion::class, 'form_type_version_id');
    }

    /**
     * @return BelongsTo<FieldType, $this>
     */
    public function fieldType(): BelongsTo
    {
        return $this->belongsTo(FieldType::class, 'field_type_id');
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function parentField(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_field_id');
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_field_id');
    }

    /**
     * @return HasMany<FormFieldOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(FormFieldOption::class, 'form_field_id');
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
