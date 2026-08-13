<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends BaseModel
{
    protected $table = 'organizations';

    protected $fillable = [
        'code', 'name', 'legal_name', 'description', 'registration_no', 'tax_no',
        'address', 'city', 'country', 'email', 'phone', 'website', 'logo_path',
        'timezone', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'organization_id');
    }

    public function locations(): HasManyThrough
    {
        return $this->hasManyThrough(Location::class, Company::class, 'organization_id', 'company_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
