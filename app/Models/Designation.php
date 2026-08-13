<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends BaseModel
{
    protected $table = 'designations';

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'designation_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
