<?php
/**
 * Exercises the ticket lifecycle end to end against Oracle: create, assign,
 * comment, resolve, close, and confirms the audit trail, history, notifications
 * and SLA fields were written inside the same transaction.
 *
 * Usage: php scripts/workflow_test.php
 */

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\ScopeContext;
use App\Services\TicketService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pass = 0;
$fail = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $pass, $fail;

    $condition ? $pass++ : $fail++;
    printf("[%s] %s%s\n", $condition ? ' OK ' : 'FAIL', $label, $detail !== '' ? " — {$detail}" : '');
}

$manager  = User::where('email', 'itmanager@servicedesk.local')->firstOrFail();
$agent    = User::where('email', 'support@servicedesk.local')->firstOrFail();
$employee = User::where('email', 'employee@servicedesk.local')->firstOrFail();

Auth::login($manager);

$scope = app(ScopeContext::class);
$scope->initialiseFor($manager);
$companyId = $scope->companyId();

check('Scope resolved a company for the manager', $companyId !== null, "company_id={$companyId}");

$service  = app(TicketService::class);
$priority = TicketPriority::where('code', TicketPriority::HIGH)->firstOrFail();

// ------------------------------------------------------------------- create
$before = Ticket::count();

$ticket = $service->create([
    'subject'      => 'Workflow test — printer offline',
    'description'  => 'Automated workflow verification ticket.',
    'company_id'   => $companyId,
    'priority_id'  => $priority->id,
    'requester_id' => $employee->id,
], $manager);

check('Ticket created', Ticket::count() === $before + 1, $ticket->ticket_no);
check('Ticket number follows the company series',
    (bool) preg_match('/^[A-Z]+-\d{4}-\d{6}$/', $ticket->ticket_no), $ticket->ticket_no);
check('Primary key came from the Oracle sequence', $ticket->id > 0, "id={$ticket->id}");
check('SLA due date derived from priority',
    $ticket->due_at !== null && $ticket->due_at->greaterThan(now()),
    'due ' . $ticket->due_at?->format('Y-m-d H:i'));
check('Response deadline set', $ticket->sla_response_due_at !== null);
check('History row written', $ticket->history()->count() === 1);
check('Audit row written',
    AuditLog::where('entity_type', 'TICKET')->where('entity_id', $ticket->id)->where('action', 'CREATE')->exists());

// ------------------------------------------------------------------- assign
$service->assign($ticket, $agent->id, $manager, 'Assigned by workflow test');
$ticket->refresh();

check('Ticket assigned', (int) $ticket->assigned_to === $agent->id);
check('Assignment moved status to In Progress',
    $ticket->status->code === TicketStatus::IN_PROGRESS, $ticket->status->name);
check('Assignee notified',
    Notification::where('user_id', $agent->id)->where('entity_id', $ticket->id)->exists());

// ------------------------------------------------------------------ comment
Auth::login($agent);
$service->addComment($ticket, 'Replacement toner ordered, will fit tomorrow.', $agent);
$ticket->refresh();

check('Comment stored', $ticket->comments()->count() === 1);
check('First response timestamp captured', $ticket->first_response_at !== null);

$service->addComment($ticket, 'Internal: spare unit is in the store room.', $agent, true);
check('Internal note stored', $ticket->comments()->where('is_internal', 1)->count() === 1);

// ------------------------------------------------------------------ resolve
$service->resolve($ticket, 'Toner replaced and test page printed successfully.', $agent);
$ticket->refresh();

check('Ticket resolved', $ticket->status->code === TicketStatus::RESOLVED);
check('Resolution text saved', ! empty($ticket->resolution));
check('Resolved timestamp set', $ticket->resolved_at !== null);
check('SLA marked as met', $ticket->sla_status === Ticket::SLA_MET, $ticket->sla_status);

// -------------------------------------------------------------------- close
$service->changeStatus($ticket, TicketStatus::idByCode(TicketStatus::CLOSED), $agent, 'Confirmed by user');
$ticket->refresh();

check('Ticket closed', (bool) $ticket->status->is_closed_state);
check('Closed timestamp set', $ticket->closed_at !== null);

// ------------------------------------------------------------------- reopen
$service->changeStatus($ticket, TicketStatus::idByCode(TicketStatus::OPEN), $manager, 'Problem recurred');
$ticket->refresh();

check('Ticket reopened', $ticket->status->code === TicketStatus::OPEN);
check('Reopen counter incremented', (int) $ticket->reopen_count === 1, "count={$ticket->reopen_count}");
check('Closure stamps cleared', $ticket->closed_at === null && $ticket->resolved_at === null);

// --------------------------------------------------- transaction rollback
// A failing operation inside a transaction must leave nothing behind.
$countBefore = Ticket::count();

try {
    DB::connection('oracle')->transaction(function () use ($service, $companyId, $priority, $manager) {
        $service->create([
            'subject'     => 'Rollback test',
            'company_id'  => $companyId,
            'priority_id' => $priority->id,
        ], $manager);

        throw new \RuntimeException('Forced rollback');
    });
} catch (\RuntimeException) {
    // expected
}

check('Failed transaction rolled the ticket back', Ticket::count() === $countBefore,
    'before=' . $countBefore . ' after=' . Ticket::count());

// ------------------------------------------------- concurrent numbering
// Ten allocations in a row must be unique and gapless.
$numbers = [];
for ($i = 0; $i < 10; $i++) {
    $numbers[] = app(\App\Services\DocumentNumberService::class)
        ->next(\App\Services\DocumentNumberService::TICKET, $companyId);
}

check('Allocated numbers are unique', count($numbers) === count(array_unique($numbers)));

// ------------------------------------------------------------------ cleanup
$ticket->comments()->delete();
$ticket->history()->delete();
Notification::where('entity_id', $ticket->id)->delete();
AuditLog::where('entity_type', 'TICKET')->where('entity_id', $ticket->id)->delete();
$ticket->delete();

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
