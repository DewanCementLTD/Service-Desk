<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends BaseModel
{
    protected $table = 'tasks';

    protected $fillable = [
        'task_no', 'company_id', 'location_id', 'title', 'description',
        'task_status_id', 'priority_id', 'parent_task_id', 'department_id',
        'assigned_to', 'assigned_by', 'assigned_at', 'due_at', 'started_at',
        'completed_at', 'completed_by', 'progress_percent', 'estimated_hours',
        'actual_hours', 'is_overdue', 'remarks', 'board_rank', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'company_id'       => 'integer',
        'location_id'      => 'integer',
        'assigned_at'      => 'datetime',
        'due_at'           => 'datetime',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'progress_percent' => 'integer',
        'estimated_hours'  => 'float',
        'actual_hours'     => 'float',
        'is_overdue'       => 'boolean',
        'board_rank'       => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')->orderBy('created_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_all_companies) {
            $query->whereIn('company_id', $user->accessibleCompanyIds() ?: [0]);
        }

        return $query;
    }

    public function scopeInScope(Builder $query, ?int $companyId, ?int $locationId = null): Builder
    {
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! ($this->status?->is_completed_state);
    }
}
