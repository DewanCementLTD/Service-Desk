<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistory extends BaseModel
{
    protected $table = 'ticket_history';

    /** History rows are append-only, so there is no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id', 'user_id', 'action', 'field_name', 'old_value', 'new_value', 'notes',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
