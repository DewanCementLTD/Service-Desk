<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\Department;
use App\Models\DevelopmentRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\AgentDirectory;
use App\Services\AuditLogger;
use App\Services\ScopeContext;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private ScopeContext $scope,
        private AuditLogger $audit,
        private AgentDirectory $agentDirectory,
    ) {
    }

    /**
     * The intake chooser. Users raise two different things — something is broken,
     * or something new is needed — and each goes to its own register, so the
     * choice is made before the form rather than buried in a dropdown inside it.
     */
    public function chooseType(Request $request): View
    {
        return view('tickets.choose', [
            'canRaiseDevelopment' => $request->user()->hasPermission('development.create'),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Ticket::with(['status', 'priority', 'requester', 'assignee', 'department', 'location'])
            ->visibleTo($user)
            ->inScope($this->scope->companyId(), $this->scope->locationId());

        // Users without the "view all" permission only see their own traffic.
        if (! $user->hasPermission('tickets.view_all')) {
            $query->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('assigned_to', $user->id);
            });
        }

        $this->applyFilters($query, $request);

        $tickets = $query
            ->orderByDesc($request->string('sort', 'created_at')->toString() === 'due_at' ? 'due_at' : 'created_at')
            ->paginate(20)
            ->withQueryString();

        return view('tickets.index', [
            'tickets'              => $tickets,
            'statuses'             => TicketStatus::active()->ordered()->get(),
            'priorities'           => TicketPriority::active()->ordered()->get(),
            'categories'           => TicketCategory::active()->ordered()->get(),
            'agents'               => $this->agents(),
            'developmentRequests'  => $user->hasPermission('development.view') ? $this->recentDevelopmentRequests($user) : null,
            'filters'    => $request->only(['q', 'status_id', 'priority_id', 'category_id', 'assigned_to', 'sla', 'from', 'to']),
        ]);
    }

    public function create(): View
    {
        return view('tickets.create', $this->formData());
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $this->tickets->create($request->validated(), $request->user());

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($request, $ticket);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_no} has been created.");
    }

    public function show(Ticket $ticket): View
    {
        $this->authoriseAccess($ticket);

        $ticket->load([
            'status', 'priority', 'type', 'category', 'requester', 'assignee',
            'department', 'company', 'location', 'resolver',
            'comments.user', 'comments.attachments', 'history.user', 'attachments.uploader',
        ]);

        $user = auth()->user();

        return view('tickets.show', [
            'ticket'     => $ticket,
            'statuses'   => TicketStatus::active()->ordered()->get(),
            'agents'     => $this->agents(),
            // Internal notes stay inside the IT team.
            'canSeeInternal' => $user->hasPermission('tickets.comment_internal'),
        ]);
    }

    public function edit(Ticket $ticket): View
    {
        $this->authoriseAccess($ticket);
        abort_unless(auth()->user()->hasPermission('tickets.update'), 403);

        return view('tickets.edit', array_merge($this->editFormData(), ['ticket' => $ticket]));
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);
        abort_unless($request->user()->hasPermission('tickets.update'), 403);

        $data = $request->validate([
            'subject'       => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string', 'max:20000'],
            'category_id'   => ['required', 'integer'],
            'priority_id'   => ['required', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'location_id'   => ['nullable', 'integer',
                Rule::exists('oracle.locations', 'id')
                    ->where(fn ($q) => $q->where('company_id', $ticket->company_id))],
        ]);

        $original = $ticket->getOriginal();

        DB::connection('oracle')->transaction(function () use ($ticket, $data, $request, $original) {
            $ticket->update($data + ['updated_by' => $request->user()->id]);

            $this->tickets->recordHistory($ticket, 'UPDATED', null, null, null, 'Ticket details updated');

            $this->audit->updated(
                'TICKET',
                $ticket->id,
                $original,
                $ticket->getChanges(),
                "Ticket {$ticket->ticket_no} updated"
            );
        });

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);
        abort_unless($request->user()->hasPermission('tickets.assign'), 403);

        // Only an agent eligible to work this company's queue may be named — the
        // id arrives from a form field and must not be taken on trust.
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::in($this->agents()->modelKeys())],
            'note'        => ['nullable', 'string', 'max:1000'],
        ], [
            'assigned_to.in' => 'That person is not part of the IT team for this company.',
        ]);

        $this->tickets->assign($ticket, (int) $data['assigned_to'], $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Ticket assigned.');
    }

    public function changeStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);
        abort_unless($request->user()->hasAnyPermission('tickets.update', 'tickets.resolve', 'tickets.close'), 403);

        $data = $request->validate([
            'status_id' => ['required', 'integer'],
            'note'      => ['nullable', 'string', 'max:1000'],
        ]);

        $this->tickets->changeStatus($ticket, (int) $data['status_id'], $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Ticket status updated.');
    }

    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);
        abort_unless($request->user()->hasPermission('tickets.resolve'), 403);

        $data = $request->validate([
            'resolution' => ['required', 'string', 'max:20000'],
        ]);

        $this->tickets->resolve($ticket, $data['resolution'], $request->user());

        return back()->with('success', 'Ticket resolved.');
    }

    public function comment(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);

        $data = $request->validate([
            'body'          => ['required', 'string', 'max:20000'],
            'is_internal'   => ['nullable', 'boolean'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $internal = $request->boolean('is_internal')
            && $request->user()->hasPermission('tickets.comment_internal');

        $comment = $this->tickets->addComment($ticket, $data['body'], $request->user(), $internal);

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($request, $ticket, $comment->id);
        }

        return back()->with('success', 'Comment added.');
    }

    /** Rate a resolved ticket - only the requester may do this. */
    public function rate(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);

        abort_unless((int) $ticket->requester_id === $request->user()->id, 403,
            'Only the person who raised the ticket can rate it.');

        // Feedback is about the work done, so there has to be work to judge.
        abort_unless($ticket->resolved_at !== null || $ticket->isClosed(), 400,
            'This ticket has not been resolved yet.');

        $data = $request->validate([
            'satisfaction_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'satisfaction_note'   => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->update($data);

        return back()->with('success', 'Thank you for your feedback.');
    }

    public function downloadAttachment(TicketAttachment $attachment)
    {
        $this->authoriseAccess($attachment->ticket);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authoriseAccess($ticket);
        abort_unless(auth()->user()->hasPermission('tickets.delete'), 403);

        $attributes = $ticket->getAttributes();
        $number     = $ticket->ticket_no;

        DB::connection('oracle')->transaction(function () use ($ticket, $attributes, $number) {
            $this->audit->deleted('TICKET', $ticket->id, $attributes, "Ticket {$number} deleted");
            $ticket->delete();
        });

        return redirect()->route('tickets.index')->with('success', "Ticket {$number} deleted.");
    }

    // ------------------------------------------------------------------ helpers

    /** Block access to tickets outside the user's company/location grants. */
    private function authoriseAccess(Ticket $ticket): void
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany((int) $ticket->company_id), 403,
            'This ticket belongs to a company you do not have access to.');

        if ($ticket->location_id !== null && ! $user->canAccessLocation((int) $ticket->location_id)) {
            abort(403, 'This ticket belongs to a location you do not have access to.');
        }

        // Requesters and assignees always see their own tickets.
        if (! $user->hasPermission('tickets.view_all')
            && (int) $ticket->requester_id !== $user->id
            && (int) $ticket->assigned_to !== $user->id) {
            abort(403, 'You can only view tickets you raised or are assigned to.');
        }
    }

    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . strtoupper($request->string('q')) . '%';
                $q->where(function ($w) use ($term) {
                    $w->whereRaw('UPPER(TICKET_NO) LIKE ?', [$term])
                        ->orWhereRaw('UPPER(SUBJECT) LIKE ?', [$term]);
                });
            })
            ->when($request->filled('status_id'), fn ($q) => $q->where('status_id', $request->integer('status_id')))
            ->when($request->filled('priority_id'), fn ($q) => $q->where('priority_id', $request->integer('priority_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->input('sla') === 'breached', fn ($q) => $q->where('sla_status', Ticket::SLA_BREACHED))
            ->when($request->input('sla') === 'overdue', fn ($q) => $q->overdue())
            ->when($request->input('sla') === 'unassigned', fn ($q) => $q->whereNull('assigned_to'));
    }

    private function storeAttachments(Request $request, Ticket $ticket, ?int $commentId = null): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("tickets/{$ticket->id}", 'local');

            TicketAttachment::create([
                'ticket_id'   => $ticket->id,
                'comment_id'  => $commentId,
                'uploaded_by' => $request->user()->id,
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $path,
                'mime_type'   => $file->getClientMimeType(),
                'file_size'   => $file->getSize(),
            ]);
        }
    }

    /** IT staff eligible to receive tickets in the active company. */
    private function agents()
    {
        return $this->agentDirectory->forCompany($this->scope->companyId());
    }

    /**
     * A snapshot of open development requests, shown on the Tickets screen so
     * IT staff working the queue see everything raised against them — not just
     * incidents — without leaving the page. Mirrors the visibility rule in
     * DevelopmentRequestController::index() (own requests only, unless the
     * viewer manages the pipeline) and excludes anything already resolved,
     * released or rejected: this is a "what's still moving" view, not the full
     * register — that lives on the Development screen itself.
     */
    private function recentDevelopmentRequests(User $user)
    {
        return DevelopmentRequest::with(['status', 'priority', 'requester', 'assignee'])
            ->visibleTo($user)
            ->inScope($this->scope->companyId(), $this->scope->locationId())
            ->whereHas('status', fn ($q) => $q->where('is_completed_state', 0))
            ->when(! $user->hasAnyPermission('development.update', 'development.approve', 'development.assign'), function ($q) use ($user) {
                $q->where(fn ($w) => $w->where('requester_id', $user->id)->orWhere('assigned_to', $user->id));
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /** Fields offered when raising a ticket. */
    private function formData(): array
    {
        return [
            'categories' => TicketCategory::active()->ordered()->get(),
            'priorities' => TicketPriority::active()->ordered()->get(),
            'agents'     => $this->agents(),
        ];
    }

    /**
     * Fields offered when correcting a ticket. IT can move a ticket to another
     * department here — that is a correction, not something the requester picks.
     */
    private function editFormData(): array
    {
        return $this->formData() + [
            'departments' => Department::selectableFor(auth()->user(), $this->scope->companyId()),
        ];
    }
}
