<?php

namespace App\Models;

class TicketStatus extends LookupModel
{
    protected $table = 'ticket_statuses';

    protected $fillable = [
        'code', 'name', 'description', 'color', 'is_open_state', 'is_closed_state',
        'stops_sla_clock', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_open_state'   => 'boolean',
        'is_closed_state' => 'boolean',
        'stops_sla_clock' => 'boolean',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    public const OPEN        = 'OPEN';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const PENDING     = 'PENDING';
    public const RESOLVED    = 'RESOLVED';
    public const CLOSED      = 'CLOSED';
    public const CANCELLED   = 'CANCELLED';
}
