<?php

namespace App\Services;

use App\Jobs\SendDevelopmentRequestEmail;
use App\Mail\DevelopmentRequestMail;
use App\Models\DevelopmentRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AgentDirectory;
use Illuminate\Support\Facades\Log;

/**
 * Emails the full development-request audience on every lifecycle event:
 * creation, status/approval change, and progress updates. Mirrors
 * TicketMailService — see there for the general shape.
 *
 * The audience is the same on every event, so all the mail for one request
 * reads as a single thread (see DevelopmentRequestMail::headers()):
 *   - the company's development/IT team (see AgentDirectory — includes the
 *     DEVELOPER role, not just IT_MANAGER/IT_STAFF)
 *   - DGM users for the company (the request's approvers — mirrors the
 *     notifyRoles([IT_MANAGER, DGM]) audience used for the in-app notification
 *     on creation, see DevelopmentRequestController::store())
 *   - the fixed IT lead from config('servicedesk.it_lead')
 *   - the requester and their head of department
 *   - the assigned developer, once one is named
 */
class DevelopmentRequestMailService
{
    public function __construct(private AgentDirectory $agents)
    {
    }

    public function notifyCreated(DevelopmentRequest $request, ?User $actor): void
    {
        $this->dispatch($request, DevelopmentRequestMail::CREATED, $actor);
    }

    public function notifyStatusChanged(DevelopmentRequest $request, ?User $actor, ?string $fromStatus, string $toStatus, ?string $note = null): void
    {
        $this->dispatch($request, DevelopmentRequestMail::STATUS_CHANGED, $actor, [
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'note'        => $note,
        ]);
    }

    public function notifyCommented(DevelopmentRequest $request, ?User $actor, string $body): void
    {
        $this->dispatch($request, DevelopmentRequestMail::COMMENTED, $actor, ['body' => $body]);
    }

    /** @param  array<string, mixed>  $context */
    private function dispatch(DevelopmentRequest $request, string $event, ?User $actor, array $context = []): void
    {
        $recipients = $this->recipients($request);

        if ($recipients === []) {
            return;
        }

        $job = SendDevelopmentRequestEmail::dispatch($request->id, $event, $actor?->id, $recipients, $context);

        // Mirrors NotificationService::push() — without a queue worker, sync
        // dispatch would otherwise send mail mid-request.
        if (config('queue.default') === 'sync') {
            $job->afterResponse();
        }
    }

    /** @return array<int, array{email: string, name: ?string}> */
    private function recipients(DevelopmentRequest $request): array
    {
        $byEmail = [];

        $add = function (?string $email, ?string $name) use (&$byEmail) {
            if ($email === null || $email === '') {
                return;
            }

            $byEmail[strtolower($email)] = ['email' => $email, 'name' => $name];
        };

        foreach ($this->devTeam($request) as $user) {
            $add($user->email, $user->name);
        }

        foreach ($this->approvers($request) as $user) {
            $add($user->email, $user->name);
        }

        $leadEmail = config('servicedesk.it_lead.email');
        if ($leadEmail) {
            $add($leadEmail, config('servicedesk.it_lead.name'));
        }

        $requester = $request->requester;
        if ($requester && $requester->hasReachableEmail()) {
            $add($requester->email, $requester->name);
        }

        $hod = $requester?->hod();
        if ($hod && $hod->hasReachableEmail() && $hod->id !== $requester?->id) {
            $add($hod->email, $hod->name);
        } elseif ($requester && $requester->department_id && ! $hod) {
            Log::info('Development request email: requester has no HOD on file', [
                'development_request_id' => $request->id,
                'requester_id'            => $requester->id,
                'department_id'           => $requester->department_id,
            ]);
        }

        $assignee = $request->assignee;
        if ($assignee && $assignee->hasReachableEmail()) {
            $add($assignee->email, $assignee->name);
        }

        return array_values($byEmail);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function devTeam(DevelopmentRequest $request)
    {
        return $this->agents->forCompany($request->company_id)
            ->filter(fn (User $user) => $user->hasReachableEmail());
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function approvers(DevelopmentRequest $request)
    {
        return User::active()
            ->whereHas('role', fn ($q) => $q->whereIn('code', [Role::DGM]))
            ->where(fn ($q) => $q->where('is_all_companies', 1)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $request->company_id)))
            ->get()
            ->filter(fn (User $user) => $user->hasReachableEmail());
    }
}
