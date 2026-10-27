<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'oracle';

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'employee_no', 'name', 'email', 'password', 'role_id', 'department_id',
        'default_org_id', 'default_company_id', 'default_location_id',
        'designation_id', 'phone', 'avatar_path', 'hod_phone',
        'is_all_orgs', 'is_all_companies', 'is_all_locations', 'is_active',
        'must_change_password', 'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'is_all_orgs'       => 'boolean',
        'is_all_companies'  => 'boolean',
        'is_all_locations'  => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'must_change_password' => 'boolean',
        'password_changed_at'  => 'datetime',
    ];

    /** Cached permission codes for the current request. */
    private ?array $permissionCodes = null;

    // ------------------------------------------------------------ relationships

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function defaultOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'default_org_id');
    }

    public function defaultCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'default_company_id');
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'user_organizations', 'user_id', 'organization_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies', 'user_id', 'company_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'user_locations', 'user_id', 'location_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function teamLeadAssignments(): HasMany
    {
        return $this->hasMany(TeamAssignment::class, 'team_lead_id');
    }

    /**
     * The users reporting to this team lead. pluck() drops out of the Eloquent
     * collection into the base one, so this cannot be typed as Eloquent\Collection.
     */
    public function teamMembers(): SupportCollection
    {
        return $this->teamLeadAssignments()
            ->with('teamMember')
            ->get()
            ->pluck('teamMember')
            ->filter()
            ->values();
    }

    /** The row naming this user as somebody's team member. */
    public function teamLeadAssignment(): HasOne
    {
        return $this->hasOne(TeamAssignment::class, 'team_member_id');
    }

    public function teamLead(): ?User
    {
        return $this->teamLeadAssignment->teamLead ?? null;
    }

    // ------------------------------------------------------ access to org scope

    /**
     * Organisations this user may work in. A user flagged IS_ALL_ORGS sees every
     * active organisation without needing individual grant rows.
     */
    public function accessibleOrganizations(): Collection
    {
        if ($this->is_all_orgs) {
            return Organization::active()->orderBy('name')->get();
        }

        return $this->organizations()->where('organizations.is_active', 1)->orderBy('name')->get();
    }

    public function accessibleCompanies(?int $organizationId = null): Collection
    {
        $query = Company::active()->orderBy('name');

        if (! $this->is_all_companies) {
            $query->whereIn('id', $this->companies()->pluck('companies.id'));
        }

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query->get();
    }

    public function accessibleLocations(?int $companyId = null): Collection
    {
        $query = Location::active()->orderBy('name');

        if (! $this->is_all_locations) {
            $query->whereIn('id', $this->locations()->pluck('locations.id'));
        }

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->get();
    }

    public function canAccessOrganization(int $organizationId): bool
    {
        return $this->is_all_orgs
            || $this->organizations()->where('organizations.id', $organizationId)->exists();
    }

    public function canAccessCompany(int $companyId): bool
    {
        return $this->is_all_companies
            || $this->companies()->where('companies.id', $companyId)->exists();
    }

    public function canAccessLocation(int $locationId): bool
    {
        return $this->is_all_locations
            || $this->locations()->where('locations.id', $locationId)->exists();
    }

    /** Company ids usable in whereIn() when scoping a query to this user. */
    public function accessibleCompanyIds(): array
    {
        if ($this->is_all_companies) {
            return Company::active()->pluck('id')->all();
        }

        return $this->companies()->pluck('companies.id')->all();
    }

    public function accessibleLocationIds(): array
    {
        if ($this->is_all_locations) {
            return Location::active()->pluck('id')->all();
        }

        return $this->locations()->pluck('locations.id')->all();
    }

    // ------------------------------------------------------- roles & permissions

    public function hasRole(string ...$codes): bool
    {
        return in_array($this->role?->code, $codes, true);
    }

    /** Lower hierarchy_level means more authority (1 = system administrator). */
    public function hasRoleLevelAtMost(int $level): bool
    {
        return ($this->role?->hierarchy_level ?? 999) <= $level;
    }

    public function permissionCodes(): array
    {
        if ($this->permissionCodes === null) {
            $this->permissionCodes = $this->role
                ? $this->role->permissions()->pluck('permissions.code')->all()
                : [];
        }

        return $this->permissionCodes;
    }

    public function hasPermission(string $code): bool
    {
        // The system administrator role carries an explicit wildcard permission.
        $codes = $this->permissionCodes();

        return in_array('*', $codes, true) || in_array($code, $codes, true);
    }

    public function hasAnyPermission(string ...$codes): bool
    {
        foreach ($codes as $code) {
            if ($this->hasPermission($code)) {
                return true;
            }
        }

        return false;
    }

    /** Public URL of the uploaded photo, or null when the initials should show. */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

        return $initials !== '' ? $initials : 'U';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * This user's head of department, i.e. their department's HEAD_USER_ID.
     * Null when the user has no department, or the department has no head set.
     */
    public function hod(): ?User
    {
        return $this->department?->head;
    }

    /**
     * False for the placeholder addresses DmlEmployeeSeeder assigns to accounts
     * imported without a real mailbox (see that seeder's docblock) — mail must
     * never be sent to those.
     */
    public function hasReachableEmail(): bool
    {
        $domain = '@' . config('servicedesk.placeholder_mail_domain');

        return $this->email !== null && ! str_ends_with(strtolower($this->email), strtolower($domain));
    }
}
