<?php

namespace App\Models;

use App\Casts\SanitizedRichText;
use App\Enums\RespondentType;
use App\Policies\FormPublicationPolicy;
use Database\Factories\FormPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'company_id',
    'form_type_id',
    'form_type_version_id',
    'name',
    'slug',
    'respondent_type',
    'starts_at',
    'ends_at',
    'allow_draft',
    'allow_edit_after_submit',
    'show_progress',
    'show_question_numbers',
    'max_responses_per_respondent',
    'thank_you_title',
    'thank_you_description',
    'is_active',
    'created_by',
    'updated_by',
])]
#[UsePolicy(FormPublicationPolicy::class)]
class FormPublication extends Model
{
    /** @use HasFactory<FormPublicationFactory> */
    use HasFactory;

    public $table = 'form_publications';

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(function (FormPublication $publication): void {
            $publication->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'respondent_type' => RespondentType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'allow_draft' => 'boolean',
            'allow_edit_after_submit' => 'boolean',
            'show_progress' => 'boolean',
            'show_question_numbers' => 'boolean',
            'max_responses_per_respondent' => 'integer',
            'thank_you_description' => SanitizedRichText::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * @return BelongsTo<FormType, $this>
     */
    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_id');
    }

    /**
     * @return BelongsTo<FormTypeVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormTypeVersion::class, 'form_type_version_id');
    }

    /**
     * @return HasMany<FormAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class, 'form_publication_id');
    }

    /**
     * @return HasMany<FormResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class, 'form_publication_id');
    }

    /**
     * @return HasMany<FormPublicationAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(FormPublicationAudience::class, 'form_publication_id');
    }

    /**
     * @return HasOne<FormPublicationAudience, $this>
     */
    public function currentAudience(): HasOne
    {
        return $this->hasOne(FormPublicationAudience::class, 'form_publication_id')
            ->ofMany(['resolved_at' => 'max'], fn ($query) => $query->where('is_current', true));
    }

    /**
     * @return HasMany<FormInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(FormInvitation::class, 'form_publication_id');
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
