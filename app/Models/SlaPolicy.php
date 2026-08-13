<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaPolicy extends BaseModel
{
    protected $table = 'sla_policies';

    protected $fillable = [
        'code', 'name', 'description', 'company_id', 'priority_id', 'type_id',
        'response_hours', 'resolution_hours', 'is_active',
    ];

    protected $casts = [
        'response_hours'   => 'integer',
        'resolution_hours' => 'integer',
        'is_active'        => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
