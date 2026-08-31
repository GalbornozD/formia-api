<?php

namespace App\Models;

use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Policies\FormInvitationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'company_id',
    'form_publication_id',
    'form_assignment_id',
    'guest_respondent_id',
    'channel',
    'recipient',
    'token_hash',
    'status',
    'expires_at',
    'sent_at',
    'opened_at',
    'used_at',
    'created_by',
])]
#[UsePolicy(FormInvitationPolicy::class)]
class FormInvitation extends Model
{
    public $table = 'form_invitations';

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(function (FormInvitation $invitation): void {
            $invitation->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'channel' => InvitationChannel::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'used_at' => 'datetime',
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
     * @return BelongsTo<FormAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FormAssignment::class, 'form_assignment_id');
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
