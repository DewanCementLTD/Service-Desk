<?php

namespace App\Models;

class TaskStatus extends LookupModel
{
    protected $table = 'task_statuses';

    protected $fillable = [
        'code', 'name', 'description', 'color', 'is_completed_state', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_completed_state' => 'boolean',
        'is_active'          => 'boolean',
        'sort_order'         => 'integer',
    ];

    public const PENDING     = 'PENDING';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const COMPLETED   = 'COMPLETED';
    public const ON_HOLD     = 'ON_HOLD';
    public const CANCELLED   = 'CANCELLED';
}
