<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends BaseModel
{
    protected $table = 'approvals';

    protected $fillable = [
        'company_id', 'entity_type', 'entity_id', 'level_no', 'requested_by',
        'approver_id', 'status', 'remarks', 'acted_at', 'due_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'level_no'  => 'integer',
        'acted_at'  => 'datetime',
        'due_at'    => 'datetime',
    ];

    public const PENDING   = 'PENDING';
    public const APPROVED  = 'APPROVED';
    public const REJECTED  = 'REJECTED';
    public const CANCELLED = 'CANCELLED';

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }
}
