<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

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

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class, 'task_id')
            ->orderBy('sort_order');
    }

    // pluck() drops out of the Eloquent collection into the base one, so this
    // cannot be typed as Eloquent\Collection.
    public function allAssignees(): SupportCollection
    {
        return $this->assignees()->with('assignee')->get()->pluck('assignee');
    }

    /**
     * Everyone working the task: the primary assignee plus the co-assignees
     * held in TASK_ASSIGNEES, in order, without duplicates.
     */
    public function assigneeTeam(): SupportCollection
    {
        return collect([$this->assignee])
            ->concat($this->assignees->pluck('assignee'))
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * A task belongs to someone if they hold it directly or ride along in
     * TASK_ASSIGNEES — every "is this mine?" filter has to ask both.
     */
    public function scopeForAssignee(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('assigned_to', $userId)
            ->orWhereHas('assignees', fn ($sq) => $sq->where('user_id', $userId)));
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
