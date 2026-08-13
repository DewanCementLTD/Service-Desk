<?php

namespace App\Models;

class TicketPriority extends LookupModel
{
    protected $table = 'ticket_priorities';

    protected $fillable = [
        'code', 'name', 'description', 'color', 'response_hours', 'resolution_hours',
        'is_critical', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'response_hours'   => 'integer',
        'resolution_hours' => 'integer',
        'is_critical'      => 'boolean',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
    ];

    public const LOW      = 'LOW';
    public const MEDIUM   = 'MEDIUM';
    public const HIGH     = 'HIGH';
    public const CRITICAL = 'CRITICAL';
}
