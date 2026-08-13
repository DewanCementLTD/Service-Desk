<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentRequest extends BaseModel
{
    protected $table = 'development_requests';

    protected $fillable = [
        'request_no', 'company_id', 'location_id', 'title', 'business_purpose',
        'requirements', 'technical_notes', 'expected_benefit', 'requester_id',
        'department_id', 'priority_id', 'development_status_id', 'assigned_to',
        'assigned_by', 'assigned_at', 'ticket_id', 'target_date', 'started_at',
        'due_at', 'completed_at', 'released_at', 'progress_percent',
        'estimated_hours', 'actual_hours', 'approval_status', 'approved_by',
        'approved_at', 'rejection_reason', 'is_overdue', 'created_by', 'updated_by',
        // Board placement: null sprint means the request sits in the backlog.
        'sprint_id', 'board_rank',
    ];

    protected $casts = [
        'company_id'       => 'integer',
        'location_id'      => 'integer',
        'assigned_at'      => 'datetime',
        'target_date'      => 'date',
        'started_at'       => 'datetime',
        'due_at'           => 'datetime',
        'completed_at'     => 'datetime',
        'released_at'      => 'datetime',
        'approved_at'      => 'datetime',
        'progress_percent' => 'integer',
        'estimated_hours'  => 'float',
        'actual_hours'     => 'float',
        'is_overdue'       => 'boolean',
        'sprint_id'        => 'integer',
        'board_rank'       => 'float',
    ];

    public const APPROVAL_PENDING   = 'PENDING';
    public const APPROVAL_APPROVED  = 'APPROVED';
    public const APPROVAL_REJECTED  = 'REJECTED';
    public const APPROVAL_ON_HOLD   = 'ON_HOLD';
    public const APPROVAL_CANCELLED = 'CANCELLED';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DevelopmentStatus::class, 'development_status_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(DevelopmentUpdate::class, 'request_id')->orderByDesc('created_at');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }

    /** Approved work not yet committed to an iteration. */
    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->whereNull('sprint_id')
            ->whereHas('status', fn ($q) => $q->where('is_completed_state', 0));
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
