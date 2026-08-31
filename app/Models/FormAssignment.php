<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\RespondentType;
use App\Policies\FormAssignmentPolicy;
use Database\Factories\FormAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'company_id',
    'form_publication_id',
    'respondent_type',
    'user_id',
    'guest_respondent_id',
    'status',
    'form_publication_audience_id',
    'assigned_at',
    'started_at',
    'submitted_at',
    'created_by',
])]
#[UsePolicy(FormAssignmentPolicy::class)]
class FormAssignment extends Model
{
    /** @use HasFactory<FormAssignmentFactory> */
    use HasFactory;

    public $table = 'form_assignments';

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(function (FormAssignment $assignment): void {
            $assignment->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'respondent_type' => RespondentType::class,
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
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
     * @return BelongsTo<FormPublicationAudience, $this>
     */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(FormPublicationAudience::class, 'form_publication_audience_id');
    }

    /**
     * @return BelongsToMany<FormPublicationAudienceSource, $this>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            FormPublicationAudienceSource::class,
            'form_assignment_sources',
            'form_assignment_id',
            'form_publication_audience_source_id',
        );
    }

    /**
     * @return HasOne<FormInvitation, $this>
     */
    public function invitation(): HasOne
    {
        return $this->hasOne(FormInvitation::class, 'form_assignment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<FormResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class, 'form_assignment_id');
    }
}
