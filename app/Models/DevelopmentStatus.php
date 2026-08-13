<?php

namespace App\Models;

class DevelopmentStatus extends LookupModel
{
    protected $table = 'development_statuses';

    protected $fillable = [
        'code', 'name', 'description', 'color', 'is_completed_state', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_completed_state' => 'boolean',
        'is_active'          => 'boolean',
        'sort_order'         => 'integer',
    ];

    public const REQUESTED  = 'REQUESTED';
    public const ANALYSIS   = 'ANALYSIS';
    public const APPROVED   = 'APPROVED';
    public const DEVELOPING = 'DEVELOPING';
    public const TESTING    = 'TESTING';
    public const UAT        = 'UAT';
    public const DEPLOYED   = 'DEPLOYED';
    public const ON_HOLD    = 'ON_HOLD';
    public const REJECTED   = 'REJECTED';
}
