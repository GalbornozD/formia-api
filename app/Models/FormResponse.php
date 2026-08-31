<?php

namespace App\Models;

use App\Enums\FormResponseStatus;
use App\Enums\RespondentType;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Policies\FormResponsePolicy;
use Database\Factories\FormResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'company_id',
    'form_publication_id',
    'form_type_version_id',
    'form_assignment_id',
    'respondent_type',
    'user_id',
    'guest_respondent_id',
    'status',
    'started_at',
    'last_saved_at',
    'submitted_at',
    'access_token_hash',
    'locale',
])]
#[UsePolicy(FormResponsePolicy::class)]
class FormResponse extends Model
{
    /** @use HasFactory<FormResponseFactory> */
    use HasFactory, HasUuidPrimaryKey;

    public $table = 'form_responses';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'respondent_type' => RespondentType::class,
            'status' => FormResponseStatus::class,
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<FormPublication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(FormPublication::class, 'form_publication_id');
    }

    /**
     * @return BelongsTo<FormTypeVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormTypeVersion::class, 'form_type_version_id');
    }

    /**
     * @return BelongsTo<FormAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FormAssignment::class, 'form_assignment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<GuestRespondent, $this>
     */
    public function guestRespondent(): BelongsTo
    {
        return $this->belongsTo(GuestRespondent::class, 'guest_respondent_id');
    }

    /**
     * @return HasMany<FormResponseAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(FormResponseAnswer::class, 'form_response_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === FormResponseStatus::Submitted;
    }
}
