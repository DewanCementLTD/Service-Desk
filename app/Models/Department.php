<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends BaseModel
{
    protected $table = 'departments';

    protected $fillable = [
        'company_id', 'location_id', 'code', 'name', 'description', 'head_user_id',
        'email', 'phone', 'is_it_team', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_it_team' => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * The departments a user may pick from.
     *
     * Filtering on the active company alone is not enough: with no company in
     * session `where('company_id', null)` becomes `COMPANY_ID IS NULL` and the
     * list comes back empty, and companies whose structure has not been loaded
     * yet have no departments at all. So fall back to every company the user may
     * work in, and always keep their own department in the list so an existing
     * selection never disappears.
     */
    public static function selectableFor(User $user, ?int $companyId): Collection
    {
        $companyIds = $companyId !== null ? [$companyId] : $user->accessibleCompanyIds();

        return static::active()
            ->where(function ($q) use ($companyIds, $user) {
                $q->whereIn('company_id', $companyIds ?: [0]);

                if ($user->department_id) {
                    $q->orWhere('id', $user->department_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
