<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['uuid', 'legal_name', 'rut', 'status'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    public $table = 'companies';

    public $timestamps = true;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'company_id', 'user_id')
            ->using(CompanyUser::class)
            ->withPivot(['permission', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CompanyUser, $this>
     */
    public function membresias(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }

    /**
     * @return HasMany<FormType, $this>
     */
    public function formTypes(): HasMany
    {
        return $this->hasMany(FormType::class, 'company_id');
    }

    /**
     * @return HasMany<FormPublication, $this>
     */
    public function formPublications(): HasMany
    {
        return $this->hasMany(FormPublication::class, 'company_id');
    }

    /**
     * @return HasMany<FormResponse, $this>
     */
    public function formResponses(): HasMany
    {
        return $this->hasMany(FormResponse::class, 'company_id');
    }

    /**
     * @return HasOne<CompanyBranding, $this>
     */
    public function branding(): HasOne
    {
        return $this->hasOne(CompanyBranding::class, 'company_id');
    }

    /**
     * @return HasOne<CompanySettings, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(CompanySettings::class, 'company_id');
    }
}
