<?php

namespace App\Models;

use App\Casts\BinaryIp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'company_id',
        'action',
        'entity',
        'entity_id',
        'details',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'ip_address' => BinaryIp::class,
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
