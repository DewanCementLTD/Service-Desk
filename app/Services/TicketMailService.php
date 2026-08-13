<?php

namespace App\Services;

use App\Jobs\SendTicketEmail;
use App\Mail\TicketMail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AgentDirectory;
use Illuminate\Support\Facades\Log;

/**
 * Emails the full ticket audience on every lifecycle event: creation, status
 * change (including resolve/close/reopen, which all flow through
 * TicketService::changeStatus()), and public comments.
 *
 * The audience is the same on every event, so all the mail for one ticket
 * reads as a single thread (see TicketMail::headers()):
 *   - the whole IT department for the ticket's company (see AgentDirectory —
 *     department membership, not just the IT_MANAGER/IT_STAFF role codes, so
 *     everyone staffing the desk is copied, not only the two support roles)
 *   - the fixed IT lead from config('servicedesk.it_lead')
 *   - the requester
 *   - the requester's head of department (DEPARTMENTS.HEAD_USER_ID)
 *
 * There is deliberately no compliance recipient — the COMPLIANCE_OFFICER role
 * was retired, and no replacement contact address was supplied.
 *
 * Accounts without a real mailbox (see DmlEmployeeSeeder) are silently
 * skipped rather than mailed a placeholder address.
 */
class TicketMailService
{
    public function __construct(private AgentDirectory $agents)
    {
    }

    public function notifyCreated(Ticket $ticket, ?User $actor): void
    {
        $this->dispatch($ticket, TicketMail::CREATED, $actor);
    }

    public function notifyStatusChanged(Ticket $ticket, ?User $actor, ?string $fromStatus, string $toStatus, ?string $note = null): void
    {
        $this->dispatch($ticket, TicketMail::STATUS_CHANGED, $actor, [
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'note'        => $note,
        ]);
    }

    public function notifyCommented(Ticket $ticket, ?User $actor, string $body): void
    {
        $this->dispatch($ticket, TicketMail::COMMENTED, $actor, ['body' => $body]);
    }

    /** @param  array<string, mixed>  $context */
    private function dispatch(Ticket $ticket, string $event, ?User $actor, array $context = []): void
    {
        $recipients = $this->recipients($ticket);

        if ($recipients === []) {
            return;
        }

        $job = SendTicketEmail::dispatch($ticket->id, $event, $actor?->id, $recipients, $context);

        // Mirrors NotificationService::push() — without a queue worker, sync
        // dispatch would otherwise send mail mid-request.
        if (config('queue.default') === 'sync') {
            $job->afterResponse();
        }
    }

    /** @return array<int, array{email: string, name: ?string}> */
    private function recipients(Ticket $ticket): array
    {
        $byEmail = [];

        $add = function (?string $email, ?string $name) use (&$byEmail) {
            if ($email === null || $email === '') {
                return;
            }

            $byEmail[strtolower($email)] = ['email' => $email, 'name' => $name];
        };

        foreach ($this->itTeam($ticket) as $user) {
            $add($user->email, $user->name);
        }

        $leadEmail = config('servicedesk.it_lead.email');
        if ($leadEmail) {
            $add($leadEmail, config('servicedesk.it_lead.name'));
        }

        $requester = $ticket->requester;
        if ($requester && $requester->hasReachableEmail()) {
            $add($requester->email, $requester->name);
        }

        $hod = $requester?->hod();
        if ($hod && $hod->hasReachableEmail() && $hod->id !== $requester?->id) {
            $add($hod->email, $hod->name);
        } elseif ($requester && $requester->department_id && ! $hod) {
            Log::info('Ticket email: requester has no HOD on file', [
                'ticket_id'      => $ticket->id,
                'requester_id'   => $requester->id,
                'department_id'  => $requester->department_id,
            ]);
        }

        return array_values($byEmail);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function itTeam(Ticket $ticket)
    {
        return $this->agents->forCompany($ticket->company_id)
            ->filter(fn (User $user) => $user->hasReachableEmail());
    }
}
