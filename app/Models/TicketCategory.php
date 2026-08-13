<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketCategory extends LookupModel
{
    protected $table = 'ticket_categories';

    protected $fillable = [
        'type_id', 'code', 'name', 'description', 'default_team_dept_id', 'is_active', 'sort_order',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function defaultTeam(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_team_dept_id');
    }
}
