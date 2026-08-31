<?php

namespace App\Models;

use App\Policies\DistributionListPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'company_id',
    'name',
    'description',
    'status',
    'created_by',
    'updated_by',
])]
#[UsePolicy(DistributionListPolicy::class)]
class DistributionList extends Model
{
    use HasFactory;

    public $table = 'distribution_lists';

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(function (DistributionList $list): void {
            $list->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
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
     * @return HasMany<DistributionListMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(DistributionListMember::class, 'distribution_list_id');
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
