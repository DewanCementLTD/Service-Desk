<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends BaseModel
{
    protected $table = 'companies';

    protected $fillable = [
        'organization_id', 'code', 'name', 'legal_name', 'registration_no', 'tax_no',
        'address', 'city', 'country', 'email', 'phone', 'website', 'logo_path',
        'timezone', 'currency_code', 'ticket_prefix', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'company_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'company_id');
    }

    /** Users explicitly granted access to this company. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_companies', 'company_id', 'user_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
