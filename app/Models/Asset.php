<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends BaseModel
{
    protected $table = 'assets';

    protected $fillable = [
        'asset_no', 'company_id', 'location_id', 'name', 'category', 'serial_no',
        'model', 'manufacturer', 'department_id', 'assigned_to', 'asset_status',
        'purchase_date', 'purchase_cost', 'warranty_end', 'placement', 'notes',
    ];

    protected $casts = [
        'company_id'    => 'integer',
        'location_id'   => 'integer',
        'purchase_date' => 'date',
        'warranty_end'  => 'date',
        'purchase_cost' => 'float',
    ];

    public const STATUSES = [
        'IN_USE'       => 'In use',
        'IN_STOCK'     => 'In stock',
        'UNDER_REPAIR' => 'Under repair',
        'RETIRED'      => 'Retired',
        'LOST'         => 'Lost',
        'DISPOSED'     => 'Disposed',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_all_companies) {
            $query->whereIn('company_id', $user->accessibleCompanyIds() ?: [0]);
        }

        return $query;
    }

    public function scopeInScope(Builder $query, ?int $companyId, ?int $locationId = null): Builder
    {
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }
}
