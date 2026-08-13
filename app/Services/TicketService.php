<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ticket lifecycle. Every operation that touches more than one table runs inside
 * a single Oracle transaction so a ticket, its history row, the audit entry and
 * the notification either all land or none do.
 */
class TicketService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private AuditLogger $audit,
        private NotificationService $notifications,
        private TicketMailService $mail,
        private ScopeContext $scope,
    ) {
    }

    /**
     * Create a ticket, allocate its number and apply the SLA target.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Ticket
    {
        return DB::connection('oracle')->transaction(function () use ($data, $actor) {
            $companyId = (int) ($data['company_id'] ?? $this->scope->companyId());
            $priority  = TicketPriority::findOrFail($data['priority_id']);
            $openId    = TicketStatus::idByCode(TicketStatus::OPEN);

            [$responseDue, $resolutionDue] = $this->slaTargets($companyId, $priority, $data['type_id'] ?? null);

            $ticket = Ticket::create([
                'ticket_no'           => $this->numbers->next(DocumentNumberService::TICKET, $companyId),
                'company_id'          => $companyId,
                'location_id'         => $data['location_id'] ?? $this->scope->locationId(),
                'subject'             => $data['subject'],
                'description'         => $data['description'] ?? null,
                'type_id'             => $data['type_id'] ?? null,
                'category_id'         => $data['category_id'] ?? null,
                'priority_id'         => $priority->id,
                'status_id'           => $data['status_id'] ?? $openId,
                'requester_id'        => $data['requester_id'] ?? $actor->id,
                'department_id'       => $data['department_id'] ?? $actor->department_id,
                'assigned_to'         => $data['assigned_to'] ?? null,
                'assigned_by'         => isset($data['assigned_to']) ? $actor->id : null,
                'assigned_at'         => isset($data['assigned_to']) ? now() : null,
                'due_at'              => $resolutionDue,
                'sla_response_due_at' => $responseDue,
                'sla_status'          => Ticket::SLA_ON_TRACK,
                'source'              => $data['source'] ?? 'WEB',
                'created_by'          => $actor->id,
                'updated_by'          => $actor->id,
            ]);

            $this->recordHistory($ticket, 'CREATED', null, null, null, 'Ticket created');

            $this->audit->created('TICKET', $ticket->id, $ticket->getAttributes(), "Ticket {$ticket->ticket_no} created");

            // Tell the IT team a new ticket landed; tell the assignee if pre-assigned.
            if ($ticket->assigned_to) {
                $this->notifications->notify(
                    (int) $ticket->assigned_to,
                    "Ticket {$ticket->ticket_no} assigned to you",
                    $ticket->subject,
                    'TICKET',
                    $ticket->id,
                    route('tickets.show', $ticket->id)
                );
            } else {
                $this->notifications->notifyRoles(
                    [Role::IT_MANAGER, Role::IT_STAFF],
                    $companyId,
                    "New ticket {$ticket->ticket_no}",
                    $ticket->subject,
                    'TICKET',
                    $ticket->id,
                    route('tickets.show', $ticket->id)
                );
            }

            $this->mail->notifyCreated($ticket, $actor);

            return $ticket;
        });
    }

    /** Assign (or reassign) a ticket to an agent. */
    public function assign(Ticket $ticket, int $assigneeId, User $actor, ?string $note = null): Ticket
    {
        return DB::connection('oracle')->transaction(function () use ($ticket, $assigneeId, $actor, $note) {
            $previous = $ticket->assignee?->name;
            $assignee = User::findOrFail($assigneeId);

            $ticket->update([
                'assigned_to' => $assignee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'updated_by'  => $actor->id,
            ]);

            // Picking up an unstarted ticket moves it into progress automatically.
            if ($ticket->status?->code === TicketStatus::OPEN) {
                $ticket->update(['status_id' => TicketStatus::idByCode(TicketStatus::IN_PROGRESS)]);
            }

            $this->recordHistory($ticket, 'ASSIGNED', 'assigned_to', $previous, $assignee->name, $note);

            $this->audit->log(
                'ASSIGN',
                'TICKET',
                $ticket->id,
                "Ticket {$ticket->ticket_no} assigned to {$assignee->name}",
                ['assigned_to' => $previous],
                ['assigned_to' => $assignee->name]
            );

            $this->notifications->notify(
                $assignee->id,
                "Ticket {$ticket->ticket_no} assigned to you",
                $ticket->subject,
                'TICKET',
                $ticket->id,
                route('tickets.show', $ticket->id)
            );

            return $ticket->fresh();
        });
    }

    /** Move a ticket to a new status, maintaining the resolution/closure stamps. */
    public function changeStatus(Ticket $ticket, int $statusId, User $actor, ?string $note = null): Ticket
    {
        return DB::connection('oracle')->transaction(function () use ($ticket, $statusId, $actor, $note) {
            $from   = $ticket->status;
            $to     = TicketStatus::findOrFail($statusId);
            $update = ['status_id' => $to->id, 'updated_by' => $actor->id];

            if ($to->code === TicketStatus::RESOLVED && $ticket->resolved_at === null) {
                $update['resolved_at'] = now();
                $update['resolved_by'] = $actor->id;
                $update['sla_status']  = $this->slaOutcome($ticket, now());
            }

            if ($to->is_closed_state && $ticket->closed_at === null) {
                $update['closed_at'] = now();
                $update['closed_by'] = $actor->id;
            }

            // Re-opening clears the closure stamps and counts the reopen.
            if (! $to->is_closed_state && $from?->is_closed_state) {
                $update['closed_at']    = null;
                $update['closed_by']    = null;
                $update['resolved_at']  = null;
                $update['resolved_by']  = null;
                $update['reopen_count'] = (int) $ticket->reopen_count + 1;
                $update['sla_status']   = Ticket::SLA_ON_TRACK;
            }

            $ticket->update($update);

            $this->recordHistory($ticket, 'STATUS_CHANGED', 'status_id', $from?->name, $to->name, $note);

            $this->audit->log(
                'STATUS_CHANGE',
                'TICKET',
                $ticket->id,
                "Ticket {$ticket->ticket_no}: {$from?->name} -> {$to->name}",
                ['status' => $from?->name],
                ['status' => $to->name]
            );

            $this->notifications->notifyMany(
                array_filter([$ticket->requester_id, $ticket->assigned_to]),
                "Ticket {$ticket->ticket_no} is now {$to->name}",
                $ticket->subject,
                'TICKET',
                $ticket->id,
                route('tickets.show', $ticket->id)
            );

            $this->mail->notifyStatusChanged($ticket, $actor, $from?->name, $to->name, $note);

            return $ticket->fresh();
        });
    }

    /** Resolve a ticket with a resolution note. */
    public function resolve(Ticket $ticket, string $resolution, User $actor): Ticket
    {
        return DB::connection('oracle')->transaction(function () use ($ticket, $resolution, $actor) {
            $ticket->update(['resolution' => $resolution]);

            return $this->changeStatus(
                $ticket,
                TicketStatus::idByCode(TicketStatus::RESOLVED),
                $actor,
                'Resolution recorded'
            );
        });
    }

    public function addComment(Ticket $ticket, string $body, User $actor, bool $internal = false): TicketComment
    {
        return DB::connection('oracle')->transaction(function () use ($ticket, $body, $actor, $internal) {
            $comment = TicketComment::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $actor->id,
                'body'        => $body,
                'is_internal' => $internal ? 1 : 0,
            ]);

            // The first agent reply stops the response clock.
            $isAgentReply = $actor->id !== (int) $ticket->requester_id;

            if ($isAgentReply && $ticket->first_response_at === null && ! $internal) {
                $ticket->update(['first_response_at' => now()]);
            }

            $this->recordHistory($ticket, 'COMMENTED', null, null, null, $internal ? 'Internal note added' : 'Comment added');

            // Internal notes stay inside the IT team.
            $recipients = $internal
                ? array_filter([$ticket->assigned_to])
                : array_filter([$ticket->requester_id, $ticket->assigned_to]);

            // Anyone named with @ is pulled in, as long as they could already see
            // the ticket — a mention must never widen who can read an internal note.
            $recipients = array_merge($recipients, $this->mentionedUserIds($body, $ticket, $internal));

            $this->notifications->notifyMany(
                $recipients,
                "New comment on {$ticket->ticket_no}",
                mb_substr(strip_tags($body), 0, 200),
                'TICKET',
                $ticket->id,
                route('tickets.show', $ticket->id)
            );

            // Internal notes stay inside the IT team and are never emailed out.
            if (! $internal) {
                $this->mail->notifyCommented($ticket, $actor, $body);
            }

            return $comment;
        });
    }

    /**
     * Refresh SLA flags for open tickets. Intended for the scheduler; returns the
     * number of tickets whose SLA state changed.
     */
    public function refreshSlaFlags(): int
    {
        $changed = 0;

        Ticket::with('status')
            ->whereHas('status', fn ($q) => $q->where('is_closed_state', 0))
            ->whereNotNull('due_at')
            // Once a ticket is resolved its SLA outcome (MET / BREACHED) is
            // settled and must not be recalculated into a live state.
            ->whereNull('resolved_at')
            ->chunkById(500, function ($tickets) use (&$changed) {
                foreach ($tickets as $ticket) {
                    $new     = $this->projectedSlaStatus($ticket);
                    $overdue = $ticket->due_at->isPast() ? 1 : 0;

                    // The overdue flag is tracked separately from the SLA state: a
                    // ticket that is already flagged BREACHED still has to pick the
                    // flag up on the run where its deadline actually passes.
                    if ($new === $ticket->sla_status && $overdue === (int) $ticket->is_overdue) {
                        continue;
                    }

                    $ticket->update([
                        'sla_status'    => $new,
                        'is_overdue'    => $overdue,
                        'sla_breach_at' => $new === Ticket::SLA_BREACHED
                            ? ($ticket->sla_breach_at ?? $ticket->due_at)
                            : null,
                    ]);
                    $changed++;
                }
            });

        return $changed;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * User ids named with @ in a comment.
     *
     * Matching is on the first two words of a name, which is how people actually
     * write them ("@Naeem Ahmed"). Candidates are limited to the ticket's company
     * and, for an internal note, to staff who hold the internal-notes permission —
     * a mention notifies someone, it never grants them sight of anything.
     *
     * @return array<int, int>
     */
    private function mentionedUserIds(string $body, Ticket $ticket, bool $internal): array
    {
        preg_match_all('/@([\p{L}]+(?:\s+[\p{L}]+)?)/u', $body, $matches);

        $names = array_unique(array_filter(array_map('trim', $matches[1] ?? [])));

        if ($names === []) {
            return [];
        }

        $candidates = User::active()
            ->with('role.permissions')
            ->where(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhereRaw('UPPER(NAME) LIKE ?', ['%' . strtoupper($name) . '%']);
                }
            })
            ->where(fn ($q) => $q->where('is_all_companies', 1)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $ticket->company_id)))
            ->limit(10)
            ->get();

        if ($internal) {
            $candidates = $candidates->filter(fn (User $user) => $user->hasPermission('tickets.comment_internal'));
        }

        return $candidates->modelKeys();
    }

    public function recordHistory(
        Ticket $ticket,
        string $action,
        ?string $field = null,
        ?string $old = null,
        ?string $new = null,
        ?string $notes = null
    ): void {
        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'user_id'    => Auth::id(),
            'action'     => $action,
            'field_name' => $field,
            'old_value'  => $old === null ? null : mb_substr($old, 0, 1000),
            'new_value'  => $new === null ? null : mb_substr($new, 0, 1000),
            'notes'      => $notes,
        ]);
    }

    /**
     * Resolve the response/resolution deadlines, preferring a company-specific SLA
     * policy over the hours configured on the priority itself.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function slaTargets(int $companyId, TicketPriority $priority, ?int $typeId): array
    {
        $policy = SlaPolicy::active()
            ->where('priority_id', $priority->id)
            ->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->when($typeId, fn ($q) => $q->where(fn ($w) => $w->where('type_id', $typeId)->orWhereNull('type_id')))
            // A policy bound to this company wins over the generic fallback.
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        $responseHours   = $policy->response_hours   ?? $priority->response_hours;
        $resolutionHours = $policy->resolution_hours ?? $priority->resolution_hours;

        return [now()->addHours((int) $responseHours), now()->addHours((int) $resolutionHours)];
    }

    /** Was the SLA met at the moment of resolution? */
    private function slaOutcome(Ticket $ticket, Carbon $resolvedAt): string
    {
        if ($ticket->due_at === null) {
            return Ticket::SLA_MET;
        }

        return $resolvedAt->greaterThan($ticket->due_at) ? Ticket::SLA_BREACHED : Ticket::SLA_MET;
    }

    /** Where an open ticket stands against its deadline right now. */
    private function projectedSlaStatus(Ticket $ticket): string
    {
        if ($ticket->status?->stops_sla_clock) {
            return Ticket::SLA_PAUSED;
        }

        if ($ticket->due_at->isPast()) {
            return Ticket::SLA_BREACHED;
        }

        // Inside the last 20% of the window the ticket is flagged as at risk.
        $total     = $ticket->created_at->diffInMinutes($ticket->due_at);
        $remaining = now()->diffInMinutes($ticket->due_at, false);

        return ($total > 0 && $remaining <= $total * 0.2) ? Ticket::SLA_AT_RISK : Ticket::SLA_ON_TRACK;
    }
}
