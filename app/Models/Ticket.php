<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends BaseModel
{
    protected $table = 'tickets';

    protected $fillable = [
        'ticket_no', 'company_id', 'location_id', 'subject', 'description',
        'type_id', 'category_id', 'priority_id', 'status_id', 'requester_id',
        'department_id', 'assigned_to', 'assigned_by', 'assigned_at', 'due_at',
        'first_response_at', 'resolved_at', 'resolved_by', 'closed_at', 'closed_by',
        'resolution', 'sla_status', 'sla_response_due_at', 'sla_breach_at',
        'is_overdue', 'reopen_count', 'satisfaction_rating', 'satisfaction_note',
        'source', 'created_by', 'updated_by',
        // Board placement: null sprint means the ticket sits in the backlog.
        'sprint_id', 'board_rank',
    ];

    protected $casts = [
        'company_id'          => 'integer',
        'location_id'         => 'integer',
        'assigned_at'         => 'datetime',
        'due_at'              => 'datetime',
        'first_response_at'   => 'datetime',
        'resolved_at'         => 'datetime',
        'closed_at'           => 'datetime',
        'sla_response_due_at' => 'datetime',
        'sla_breach_at'       => 'datetime',
        'is_overdue'          => 'boolean',
        'reopen_count'        => 'integer',
        'satisfaction_rating' => 'integer',
        'sprint_id'           => 'integer',
        'board_rank'          => 'float',
    ];

    public const SLA_ON_TRACK = 'ON_TRACK';
    public const SLA_AT_RISK  = 'AT_RISK';
    public const SLA_BREACHED = 'BREACHED';
    public const SLA_MET      = 'MET';
    public const SLA_PAUSED   = 'PAUSED';

    // ------------------------------------------------------------ relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id')->orderBy('created_at');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class, 'ticket_id')->orderByDesc('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }

    // ------------------------------------------------------------------- scopes

    /** Restrict to the companies/locations the given user may see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_all_companies) {
            $query->whereIn('company_id', $user->accessibleCompanyIds() ?: [0]);
        }

        if (! $user->is_all_locations) {
            $ids = $user->accessibleLocationIds();
            $query->where(function ($q) use ($ids) {
                $q->whereNull('location_id')->orWhereIn('location_id', $ids ?: [0]);
            });
        }

        return $query;
    }

    /** Restrict to the company/location currently selected in the UI. */
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('is_closed_state', 0));
    }

    /** Open work not yet committed to an iteration. */
    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->whereNull('sprint_id')
            ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0));
    }

    // ------------------------------------------------------------------ helpers

    public function isClosed(): bool
    {
        return (bool) $this->status?->is_closed_state;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && ! $this->isClosed();
    }

    public function getAgeHoursAttribute(): float
    {
        return round($this->created_at?->diffInMinutes(now()) / 60, 1);
    }
}
