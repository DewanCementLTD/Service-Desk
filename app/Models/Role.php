<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends BaseModel
{
    protected $table = 'roles';

    protected $fillable = [
        'code', 'name', 'description', 'hierarchy_level', 'is_active', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'hierarchy_level' => 'integer',
        'is_active'       => 'boolean',
        'is_system'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    // Role codes used throughout the application.
    public const ADMIN      = 'ADMIN';
    public const DGM        = 'DGM';
    public const MANAGEMENT = 'MANAGEMENT';
    public const IT_MANAGER = 'IT_MANAGER';
    public const IT_STAFF   = 'IT_STAFF';
    public const DEVELOPER  = 'DEVELOPER';
    public const DEPT_HEAD  = 'DEPT_HEAD';
    public const EMPLOYEE   = 'EMPLOYEE';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
