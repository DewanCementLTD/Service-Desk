<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAssignment extends BaseModel
{
    protected $table = 'team_assignments';

    protected $fillable = [
        'team_lead_id', 'team_member_id', 'assigned_at', 'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_member_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
