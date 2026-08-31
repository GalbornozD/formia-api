<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Policies\GuestRespondentPolicy;
use Database\Factories\GuestRespondentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(GuestRespondentPolicy::class)]
#[Fillable([
    'id',
    'company_id',
    'name',
    'email',
    'phone',
    'whatsapp_phone',
    'external_reference',
    'identity_hash',
    'metadata',
    'status',
    'created_by',
    'updated_by',
])]
class GuestRespondent extends Model
{
    /** @use HasFactory<GuestRespondentFactory> */
    use HasFactory, HasUuidPrimaryKey;

    public $table = 'guest_respondents';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'status' => 'boolean',
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
     * @return HasMany<FormResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class, 'guest_respondent_id');
    }

    /**
     * @return HasMany<FormAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class, 'guest_respondent_id');
    }

    /**
     * @return HasMany<DistributionListMember, $this>
     */
    public function distributionMemberships(): HasMany
    {
        return $this->hasMany(DistributionListMember::class, 'guest_respondent_id');
    }

    /**
     * @return HasMany<FormInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(FormInvitation::class, 'guest_respondent_id');
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
