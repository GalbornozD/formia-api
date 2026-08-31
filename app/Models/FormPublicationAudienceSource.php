<?php

namespace App\Models;

use App\Enums\AudienceSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'form_publication_audience_id',
    'source_type',
    'distribution_list_id',
    'user_id',
    'guest_respondent_id',
])]
class FormPublicationAudienceSource extends Model
{
    public $table = 'form_publication_audience_sources';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source_type' => AudienceSourceType::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormPublicationAudience, $this>
     */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(FormPublicationAudience::class, 'form_publication_audience_id');
    }

    /**
     * @return BelongsTo<DistributionList, $this>
     */
    public function distributionList(): BelongsTo
    {
        return $this->belongsTo(DistributionList::class, 'distribution_list_id');
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
     * @return HasMany<FormAssignmentSource, $this>
     */
    public function assignmentSources(): HasMany
    {
        return $this->hasMany(FormAssignmentSource::class, 'form_publication_audience_source_id');
    }
}
