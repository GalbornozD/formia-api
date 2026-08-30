<?php

namespace App\Models;

use Database\Factories\FieldTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'has_options', 'is_container', 'is_active'])]
class FieldType extends Model
{
    /** @use HasFactory<FieldTypeFactory> */
    use HasFactory;

    public $table = 'field_types';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'has_options' => 'boolean',
            'is_container' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'field_type_id');
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
