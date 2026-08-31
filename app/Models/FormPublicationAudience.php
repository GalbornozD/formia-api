<?php

namespace App\Models;

use App\Enums\RespondentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'company_id',
    'form_publication_id',
    'respondent_type',
    'is_current',
    'recipients_count',
    'resolved_by',
    'resolved_at',
])]
class FormPublicationAudience extends Model
{
    public $table = 'form_publication_audiences';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (FormPublicationAudience $audience): void {
            $audience->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'respondent_type' => RespondentType::class,
            'is_current' => 'boolean',
            'recipients_count' => 'integer',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormPublication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(FormPublication::class, 'form_publication_id');
    }

    /**
     * @return HasMany<FormPublicationAudienceSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(FormPublicationAudienceSource::class, 'form_publication_audience_id');
    }

    /**
     * @return HasMany<FormAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class, 'form_publication_audience_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
