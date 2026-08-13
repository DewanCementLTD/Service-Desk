<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends BaseModel
{
    protected $table = 'locations';

    protected $fillable = [
        'company_id', 'code', 'name', 'location_type', 'address', 'city', 'state',
        'country', 'postal_code', 'email', 'phone', 'timezone', 'manager_user_id',
        'is_head_office', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'company_id'     => 'integer',
        'is_head_office' => 'boolean',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public const TYPES = [
        'HEAD_OFFICE' => 'Head Office',
        'BRANCH'      => 'Branch',
        'PLANT'       => 'Plant',
        'WAREHOUSE'   => 'Warehouse',
        'OFFICE'      => 'Office',
        'REMOTE'      => 'Remote',
        'OTHER'       => 'Other',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'location_id');
    }

    /** Users explicitly granted access to this location. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_locations', 'location_id', 'user_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->location_type] ?? $this->location_type;
    }
}
