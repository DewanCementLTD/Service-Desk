<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A week or a month of committed work for one company's IT team.
 *
 * Anything not in a sprint is in the backlog. Exactly one weekly and one monthly
 * sprint is ACTIVE per company at a time; SprintService opens the next one and
 * closes the expired one on a schedule.
 */
class Sprint extends BaseModel
{
    protected $table = 'sprints';

    protected $fillable = [
        'company_id', 'name', 'cadence', 'starts_on', 'ends_on', 'state', 'goal',
        'closed_at', 'closed_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'starts_on'  => 'date',
        'ends_on'    => 'date',
        'closed_at'  => 'datetime',
    ];

    public const WEEKLY  = 'WEEKLY';
    public const MONTHLY = 'MONTHLY';

    public const PLANNED = 'PLANNED';
    public const ACTIVE  = 'ACTIVE';
    public const CLOSED  = 'CLOSED';

    // ------------------------------------------------------------ relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sprint_id');
    }

    public function developmentRequests(): HasMany
    {
        return $this->hasMany(DevelopmentRequest::class, 'sprint_id');
    }

    // ------------------------------------------------------------------- scopes

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $companyId === null ? $query : $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', self::ACTIVE);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('state', [self::PLANNED, self::ACTIVE]);
    }

    public function scopeCadence(Builder $query, string $cadence): Builder
    {
        return $query->where('cadence', $cadence);
    }

    // ------------------------------------------------------------------ helpers

    public function isClosed(): bool
    {
        return $this->state === self::CLOSED;
    }

    /** How much of the window has elapsed, 0–100, for the progress bar. */
    public function getElapsedPercentAttribute(): int
    {
        $start = $this->starts_on?->startOfDay();
        $end   = $this->ends_on?->endOfDay();

        if ($start === null || $end === null || $end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $total = $start->diffInMinutes($end);
        $spent = $start->diffInMinutes(Carbon::now(), false);

        return (int) max(0, min(100, round($spent / $total * 100)));
    }

    public function getDaysLeftAttribute(): int
    {
        return (int) max(0, Carbon::now()->startOfDay()->diffInDays($this->ends_on, false));
    }

    /** "Week 34 · 2026" / "August 2026" — stable, so re-running the roll is a no-op. */
    public static function nameFor(string $cadence, Carbon $date): string
    {
        return $cadence === self::WEEKLY
            ? 'Week ' . $date->isoWeek() . ' · ' . $date->isoWeekYear()
            : $date->format('F Y');
    }
}
