<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\DevelopmentRequest;
use App\Models\DevelopmentStatus;
use App\Models\Location;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

/**
 * Sample transactions so the dashboards and reports have something to show.
 *
 * Not part of DatabaseSeeder - run it explicitly:
 *     php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const SUBJECTS = [
        'Laptop will not boot after the latest update',
        'Cannot access the shared finance drive',
        'Outlook keeps asking for a password',
        'Printer on the second floor is jammed',
        'ERP posting screen throws a validation error',
        'Request a new monitor for the workstation',
        'VPN drops every few minutes',
        'Need access to the payroll module',
        'Barcode scanner not reading labels',
        'Monthly sales report shows stale figures',
        'New starter account setup required',
        'Server room temperature alert',
        'Website contact form is not sending mail',
        'Two-factor authentication reset needed',
        'Stock adjustment screen is very slow',
    ];

    public function __construct(private DocumentNumberService $numbers)
    {
    }

    public function run(): void
    {
        $this->clearExistingDemoData();

        $companies  = Company::all();
        $priorities = TicketPriority::all()->keyBy('code');
        $statuses   = TicketStatus::all()->keyBy('code');
        $types      = TicketType::all();
        $categories = TicketCategory::all();

        foreach ($companies as $company) {
            $locations = Location::where('company_id', $company->id)->get();
            $departments = Department::where('company_id', $company->id)->get();

            $requesters = $this->usersFor($company->id, [Role::EMPLOYEE, Role::DEPT_HEAD, Role::MANAGEMENT]);
            $agents     = $this->usersFor($company->id, [Role::IT_STAFF, Role::IT_MANAGER, Role::DEVELOPER]);

            if ($requesters->isEmpty() || $agents->isEmpty()) {
                continue;
            }

            $this->seedTickets($company, $locations, $departments, $requesters, $agents, $priorities, $statuses, $types, $categories);
            $this->seedDevelopmentRequests($company, $locations, $departments, $requesters, $agents, $priorities);
            $this->seedTasks($company, $locations, $departments, $agents, $priorities);
        }
    }

    /**
     * Remove previously generated sample transactions so the seeder is repeatable.
     * Guarded to non-production: this deletes every row in the transaction tables.
     */
    private function clearExistingDemoData(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('DemoDataSeeder must never run against production.');
        }

        // Children first - foreign keys are enforced.
        foreach ([
            'ticket_attachments', 'ticket_comments', 'ticket_history',
            'development_updates', 'task_comments',
            'approvals', 'notifications',
            'tickets', 'development_requests', 'tasks',
            'doc_sequences',
        ] as $table) {
            \Illuminate\Support\Facades\DB::connection('oracle')->table($table)->delete();
        }
    }

    private function seedTickets($company, $locations, $departments, $requesters, $agents, $priorities, $statuses, $types, $categories): void
    {
        // Spread tickets across the last 45 days so the trend chart has shape.
        for ($i = 0; $i < 40; $i++) {
            $createdAt = now()->subDays(random_int(0, 45))->subHours(random_int(0, 23));
            $priority  = $priorities[collect(['LOW', 'MEDIUM', 'MEDIUM', 'HIGH', 'CRITICAL'])->random()];
            $statusCode = collect(['OPEN', 'OPEN', 'IN_PROGRESS', 'PENDING', 'RESOLVED', 'CLOSED', 'CLOSED'])->random();
            $status    = $statuses[$statusCode];
            $requester = $requesters->random();
            $assigned  = $statusCode === 'OPEN' && random_int(0, 3) === 0 ? null : $agents->random();

            $dueAt = $createdAt->copy()->addHours($priority->resolution_hours);

            $resolvedAt = in_array($statusCode, ['RESOLVED', 'CLOSED'], true)
                ? $createdAt->copy()->addHours(random_int(1, (int) $priority->resolution_hours + 20))
                : null;

            $ticket = Ticket::create([
                'ticket_no'           => $this->numbers->next(DocumentNumberService::TICKET, $company->id, (int) $createdAt->format('Y')),
                'company_id'          => $company->id,
                'location_id'         => $locations->random()->id,
                'subject'             => collect(self::SUBJECTS)->random(),
                'description'         => 'Reported by the user through the service desk portal. Steps to reproduce and screenshots were supplied where available.',
                'type_id'             => $types->random()->id,
                'category_id'         => $categories->random()->id,
                'priority_id'         => $priority->id,
                'status_id'           => $status->id,
                'requester_id'        => $requester->id,
                'department_id'       => $departments->random()->id,
                'assigned_to'         => $assigned?->id,
                'assigned_by'         => $assigned ? $agents->first()->id : null,
                'assigned_at'         => $assigned ? $createdAt->copy()->addMinutes(random_int(5, 240)) : null,
                'due_at'              => $dueAt,
                'sla_response_due_at' => $createdAt->copy()->addHours($priority->response_hours),
                'first_response_at'   => $assigned ? $createdAt->copy()->addMinutes(random_int(10, 300)) : null,
                'resolved_at'         => $resolvedAt,
                'resolved_by'         => $resolvedAt ? $assigned?->id : null,
                'closed_at'           => $statusCode === 'CLOSED' ? $resolvedAt?->copy()->addHours(4) : null,
                'closed_by'           => $statusCode === 'CLOSED' ? $assigned?->id : null,
                'resolution'          => $resolvedAt ? 'Issue diagnosed and resolved; the user confirmed normal operation.' : null,
                'sla_status'          => $this->slaStatus($status, $dueAt, $resolvedAt),
                'is_overdue'          => (! $status->is_closed_state && $dueAt->isPast()) ? 1 : 0,
                'satisfaction_rating' => $statusCode === 'CLOSED' ? random_int(3, 5) : null,
                'source'              => collect(['WEB', 'EMAIL', 'PHONE', 'WALK_IN'])->random(),
                'created_by'          => $requester->id,
                'updated_by'          => $requester->id,
                'created_at'          => $createdAt,
                'updated_at'          => $createdAt,
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => $requester->id,
                'action'    => 'CREATED',
                'notes'     => 'Ticket created',
                'created_at' => $createdAt,
            ]);

            if ($assigned) {
                TicketComment::create([
                    'ticket_id'  => $ticket->id,
                    'user_id'    => $assigned->id,
                    'body'       => 'Thanks for reporting this - I am looking into it now and will update you shortly.',
                    'created_at' => $createdAt->copy()->addMinutes(random_int(15, 400)),
                    'updated_at' => $createdAt->copy()->addMinutes(random_int(15, 400)),
                ]);
            }
        }
    }

    private function seedDevelopmentRequests($company, $locations, $departments, $requesters, $agents, $priorities): void
    {
        $statuses = DevelopmentStatus::all();

        $titles = [
            'Automated month-end reconciliation report',
            'Mobile approval screen for purchase orders',
            'Customer portal self-service invoice download',
            'Barcode-driven stock take module',
            'Dashboard for production line efficiency',
            'Integration between payroll and attendance',
        ];

        foreach ($titles as $index => $title) {
            $createdAt = now()->subDays(random_int(5, 90));
            $status    = $statuses->random();

            DevelopmentRequest::create([
                'request_no'            => $this->numbers->next(DocumentNumberService::DEVELOPMENT, $company->id, (int) $createdAt->format('Y')),
                'company_id'            => $company->id,
                'location_id'           => $locations->random()->id,
                'title'                 => $title,
                'business_purpose'      => 'Reduces manual effort and improves the accuracy of the current process.',
                'requirements'          => 'Detailed requirements were captured with the requesting department and signed off.',
                'requester_id'          => $requesters->random()->id,
                'department_id'         => $departments->random()->id,
                'priority_id'           => $priorities->random()->id,
                'development_status_id' => $status->id,
                'assigned_to'           => $agents->random()->id,
                'assigned_at'           => $createdAt->copy()->addDays(2),
                'target_date'           => $createdAt->copy()->addDays(60)->toDateString(),
                'due_at'                => $createdAt->copy()->addDays(60),
                'progress_percent'      => $status->is_completed_state ? 100 : random_int(0, 85),
                'estimated_hours'       => random_int(20, 200),
                'actual_hours'          => random_int(0, 150),
                'approval_status'       => collect(['PENDING', 'APPROVED', 'APPROVED', 'REJECTED'])->random(),
                'completed_at'          => $status->is_completed_state ? $createdAt->copy()->addDays(50) : null,
                'created_at'            => $createdAt,
                'updated_at'            => $createdAt,
            ]);
        }
    }

    private function seedTasks($company, $locations, $departments, $agents, $priorities): void
    {
        $statuses = TaskStatus::all();

        $titles = [
            'Prepare the quarterly IT budget submission',
            'Roll out the new asset tagging standard',
            'Review supplier contracts for renewal',
            'Complete the disaster recovery runbook',
            'Deliver security awareness training',
            'Consolidate the reporting spreadsheets',
            'Plan the branch network upgrade',
            'Close out the internal audit findings',
        ];

        foreach ($titles as $title) {
            $createdAt = now()->subDays(random_int(2, 60));
            $status    = $statuses->random();
            $dueAt     = $createdAt->copy()->addDays(random_int(5, 45));

            Task::create([
                'task_no'          => $this->numbers->next(DocumentNumberService::TASK, $company->id, (int) $createdAt->format('Y')),
                'company_id'       => $company->id,
                'location_id'      => $locations->random()->id,
                'title'            => $title,
                'description'      => 'Assigned through the management dashboard for tracking to completion.',
                'task_status_id'   => $status->id,
                'priority_id'      => $priorities->random()->id,
                'department_id'    => $departments->random()->id,
                'assigned_to'      => $agents->random()->id,
                'assigned_by'      => $agents->first()->id,
                'assigned_at'      => $createdAt,
                'due_at'           => $dueAt,
                'progress_percent' => $status->is_completed_state ? 100 : random_int(0, 90),
                'completed_at'     => $status->is_completed_state ? $dueAt->copy()->subDays(1) : null,
                'is_overdue'       => (! $status->is_completed_state && $dueAt->isPast()) ? 1 : 0,
                'created_at'       => $createdAt,
                'updated_at'       => $createdAt,
            ]);
        }
    }

    private function slaStatus($status, $dueAt, $resolvedAt): string
    {
        if ($resolvedAt !== null) {
            return $resolvedAt->greaterThan($dueAt) ? Ticket::SLA_BREACHED : Ticket::SLA_MET;
        }

        if ($status->is_closed_state) {
            return Ticket::SLA_MET;
        }

        return $dueAt->isPast() ? Ticket::SLA_BREACHED : Ticket::SLA_ON_TRACK;
    }

    /** Active users with any of the given roles who can work in this company. */
    private function usersFor(int $companyId, array $roleCodes)
    {
        return User::active()
            ->whereHas('role', fn ($q) => $q->whereIn('code', $roleCodes))
            ->where(fn ($q) => $q->where('is_all_companies', 1)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId)))
            ->get();
    }
}
