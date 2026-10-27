<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignee extends BaseModel
{
    protected $table = 'task_assignees';

    protected $fillable = [
        'task_id', 'user_id', 'assigned_at', 'assigned_by', 'sort_order',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
