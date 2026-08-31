<?php

namespace App\Models;

use App\Enums\DistributionMemberType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distribution_list_id',
    'member_type',
    'user_id',
    'guest_respondent_id',
    'created_by',
])]
class DistributionListMember extends Model
{
    public $table = 'distribution_list_members';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'member_type' => DistributionMemberType::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DistributionList, $this>
     */
    public function list(): BelongsTo
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
