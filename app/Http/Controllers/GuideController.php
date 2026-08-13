<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\TicketPriority;
use App\Models\User;
use App\Services\ScopeContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Self-service user guide.
 *
 * One short guide per role rather than one long guide for everybody. A person
 * reads at most five numbered steps covering the job they actually do, in the
 * order they will do it — an employee never scrolls past instructions for the IT
 * queue, and IT never scrolls past instructions for raising a ticket.
 *
 * The guides live here as data so the copy sits next to the routes it points at.
 */
class GuideController extends Controller
{
    public function __construct(private ScopeContext $scope)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('guide.index', [
            'guideUser'  => $user,
            'guide'      => $this->guideFor($user),
            'priorities' => TicketPriority::active()->ordered()->get(),
            'itContacts' => $this->itContacts(),
        ]);
    }

    /**
     * @return array{title: string, intro: string, steps: array<int, array{0: string, 1: string, 2?: string, 3?: string}>}
     */
    private function guideFor(User $user): array
    {
        $guide = $this->guideForRole($user);

        // Steps carry a link or they do not; padding here keeps the view free of
        // a null check on every one of them.
        $guide['steps'] = array_map(fn (array $step) => array_pad($step, 4, null), $guide['steps']);

        return $guide;
    }

    /** @return array{title: string, intro: string, steps: array<int, array<int, string>>} */
    private function guideForRole(User $user): array
    {
        return match ($user->role?->code) {
            Role::IT_STAFF    => $this->itStaffGuide(),
            Role::IT_MANAGER  => $this->itManagerGuide(),
            Role::DEVELOPER   => $this->developerGuide(),
            Role::DGM, Role::MANAGEMENT => $this->managementGuide(),
            Role::DEPT_HEAD   => $this->departmentHeadGuide(),
            Role::ADMIN       => $this->administratorGuide(),
            default           => $this->employeeGuide(),
        };
    }

    /**
     * Steps are [heading, what to do, link label, route name]. The link is shown
     * only when the reader can actually open it.
     */
    private function employeeGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'Everything you need the service desk for, in five steps. You sign in with your '
                . 'mobile number or email address, and you have three sections: Dashboard, Tickets and this guide.',
            'steps' => [
                [
                    'Something is broken? Report it.',
                    'Tickets → Raise a request → Bugs & Issues. Give it a one-line subject, describe what '
                    . 'you were doing and what went wrong, pick ERP or Network, and set the priority '
                    . 'honestly — the priority sets the deadline IT is measured against.',
                    'Raise a request', 'tickets.new',
                ],
                [
                    'Need something that does not exist yet? Request it.',
                    'Tickets → Raise a request → New Development Request. You will be asked what the system '
                    . 'must do and what result you expect. Management approves it before any work starts.',
                    'Raise a request', 'tickets.new',
                ],
                [
                    'Follow it.',
                    'Your ticket gets a number like IT-2026-000123 — quote it whenever you chase it. Open '
                    . 'Tickets to see everything you have raised and where each one has reached.',
                    'My tickets', 'tickets.index',
                ],
                [
                    'Answer when IT asks.',
                    'Most delays are a question waiting for a reply. Add a comment on the ticket and the '
                    . 'person handling it is notified straight away. The bell at the top tells you when '
                    . 'something is waiting for you.',
                ],
                [
                    'Say whether it was fixed.',
                    'Once a ticket is resolved you can rate it one to five stars. If the problem is still '
                    . 'there, comment on the ticket instead and it reopens.',
                ],
            ],
        ];
    }

    private function itStaffGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'You work the queue. These are the five things you do every day.',
            'steps' => [
                [
                    'Start with what is assigned to you.',
                    'Tickets shows your queue. Sort by SLA state — anything At risk or Breached comes first, '
                    . 'then oldest.',
                    'My queue', 'tickets.index',
                ],
                [
                    'Reply before you fix.',
                    'The first comment you post stops the response clock and tells the requester somebody has '
                    . 'picked it up. It costs a minute and it is what the response target measures.',
                ],
                [
                    'Keep the working notes internal.',
                    'Tick "internal note" for anything the requester should not read — diagnosis, passwords '
                    . 'you reset, what you tried. Public comments go to the requester.',
                ],
                [
                    'Move the status as the work moves.',
                    'Pending stops the SLA clock when you are waiting on the requester or a supplier. Use it '
                    . 'rather than letting a ticket breach while you wait.',
                ],
                [
                    'Resolve with a real resolution.',
                    'Write what you actually did, not "done". It is what the next person reads when the same '
                    . 'fault comes back, and it is what the requester sees.',
                ],
            ],
        ];
    }

    private function itManagerGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'You own the queue and the team. Five things to run the desk.',
            'steps' => [
                [
                    'Clear the unassigned list first.',
                    'Tickets → SLA state → Unassigned. Nothing should sit there overnight: an unassigned '
                    . 'ticket is still burning its response deadline.',
                    'Unassigned tickets', 'tickets.index',
                ],
                [
                    'Assign by name.',
                    'Open the ticket and use Assign. Only you, the DGM and administrators can do this, and '
                    . 'the list is your company\'s IT team. The assignee is notified immediately.',
                ],
                [
                    'Watch the breaches, not the count.',
                    'Your dashboard lists SLA breaches ordered by how far past due they are, plus per-agent '
                    . 'throughput. That is the daily stand-up in one screen.',
                    'Dashboard', 'dashboard',
                ],
                [
                    'Keep the master data honest.',
                    'Categories, priorities and SLA policies are editable data, not code. If the desk is '
                    . 'classifying everything as "Other", fix the categories rather than the habit.',
                ],
                [
                    'Report monthly.',
                    'Reports covers volume, resolution times, SLA compliance by priority and by agent, and '
                    . 'exports to CSV for anything management asks that the screen does not answer.',
                    'Reports', 'reports.index',
                ],
            ],
        ];
    }

    private function developerGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'You deliver approved development requests. Four steps.',
            'steps' => [
                [
                    'Work from the pipeline.',
                    'Development lists what has been approved and who owns it. Nothing should be started '
                    . 'before it carries an approval — that is the point of the gate.',
                    'Development pipeline', 'development.index',
                ],
                [
                    'Read the requirements and the expected result.',
                    'Every request carries both. If they do not tell you enough to build from, ask on the '
                    . 'request rather than guessing — the requester is notified.',
                ],
                [
                    'Post progress as you go.',
                    'Add an update with a percentage and the hours spent. It rolls up into the request and '
                    . 'into the effort variance management sees.',
                ],
                [
                    'Move the status through to Deployed.',
                    'Testing, UAT and then Deployed. Deployed is terminal and stamps the completion time.',
                ],
            ],
        ];
    }

    private function managementGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'Oversight across tickets, development and tasks.',
            'steps' => [
                [
                    'Start on your dashboard.',
                    'SLA health, closure rate, ageing and per-agent throughput, plus totals from all four '
                    . 'registers. Every figure is live and every tile opens the records behind it.',
                    'Dashboard', 'dashboard',
                ],
                [
                    'Decide on development requests.',
                    'Development shows what is waiting for a decision. Approve, reject or put on hold — a '
                    . 'rejection needs a reason, and the requester is notified either way. Nothing is built '
                    . 'without this.',
                    'Development pipeline', 'development.index',
                ],
                [
                    'Compare companies.',
                    'Where you can see more than one company, the cross-company table shows them side by '
                    . 'side. The boxes at the top of the screen switch which one you are working in.',
                ],
                [
                    'Ask the register, not the team.',
                    'Reports answers volume, SLA compliance and development effort variance over any date '
                    . 'range, and exports to CSV.',
                    'Reports', 'reports.index',
                ],
            ],
        ];
    }

    private function departmentHeadGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'Your department\'s requests and the work you hand out.',
            'steps' => [
                [
                    'Raise and follow your department\'s tickets.',
                    'Tickets → Raise a request. You see what you raised and what was raised against your '
                    . 'department.',
                    'Tickets', 'tickets.index',
                ],
                [
                    'Hand out work as tasks.',
                    'Tasks are for work that is not an IT ticket — follow-ups and deliverables owned by a '
                    . 'named person with a due date. Anything past its date is flagged overdue.',
                    'Tasks', 'tasks.index',
                ],
                [
                    'Request new systems through Development.',
                    'Anything that does not exist yet goes through the development request form, with the '
                    . 'requirements and the result you expect. Management approves before work starts.',
                    'Raise a request', 'tickets.new',
                ],
            ],
        ];
    }

    private function administratorGuide(): array
    {
        return [
            'title' => 'Your guide',
            'intro' => 'You hold every permission. These are the four things only you do.',
            'steps' => [
                [
                    'Load the structure before a company transacts.',
                    'Organisations → Companies → Locations → Departments, in that order. One department per '
                    . 'company must be flagged as the IT team — that is where unassigned tickets are routed, '
                    . 'and who appears in the assignee list.',
                    'Departments', 'admin.departments.index',
                ],
                [
                    'Create users with the right grants.',
                    'A user sees only the companies and locations granted to them, and a role decides what '
                    . 'they can do with what they see. Set their department: tickets they raise are filed '
                    . 'against it automatically.',
                    'Users', 'users.index',
                ],
                [
                    'Change what a role can do, not the code.',
                    'Roles are data. Grant or revoke any permission from the checklist and every holder '
                    . 'picks it up immediately. Built-in role codes cannot change — the application matches '
                    . 'on them.',
                    'Roles', 'admin.roles.index',
                ],
                [
                    'Answer "who changed this?" from the audit trail.',
                    'Every create, update, approval and sign-in is recorded with the actor, the time, the IP '
                    . 'address and the before and after values. It is append-only — nothing in the '
                    . 'application can alter it.',
                    'Audit trail', 'audit.index',
                ],
            ],
        ];
    }

    /**
     * The IT people who work the queue in the user's active company, so the guide
     * can answer "who do I chase?" without anyone having to ask around.
     */
    private function itContacts()
    {
        $companyId = $this->scope->companyId();

        return User::active()
            ->with(['role', 'defaultLocation'])
            ->whereHas('role', fn ($q) => $q->whereIn('code', [Role::IT_MANAGER, Role::IT_STAFF]))
            ->when($companyId, fn ($q) => $q->where(fn ($w) => $w->where('is_all_companies', 1)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId))))
            ->orderBy('name')
            ->get();
    }
}
