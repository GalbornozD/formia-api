<?php

namespace App\Models;

use App\Policies\FormTypeVersionPolicy;
use Database\Factories\FormTypeVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_type_id', 'version', 'is_published', 'is_active', 'published_at'])]
#[UsePolicy(FormTypeVersionPolicy::class)]
class FormTypeVersion extends Model
{
    /** @use HasFactory<FormTypeVersionFactory> */
    use HasFactory;

    public $table = 'form_type_versions';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'form_type_id' => 'integer',
            'version' => 'integer',
            'is_published' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormType, $this>
     */
    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_id');
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_type_version_id');
    }

    /**
     * @return HasMany<FormPublication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(FormPublication::class, 'form_type_version_id');
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
