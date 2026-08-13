<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DevelopmentRequest;
use App\Models\DevelopmentStatus;
use App\Models\DevelopmentUpdate;
use App\Models\Role;
use App\Models\TicketPriority;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\NotificationService;
use App\Services\ScopeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DevelopmentRequestController extends Controller
{
    public function __construct(
        private DocumentNumberService $numbers,
        private AuditLogger $audit,
        private NotificationService $notifications,
        private ScopeContext $scope,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = DevelopmentRequest::with(['status', 'priority', 'requester', 'assignee', 'department'])
            ->visibleTo($user)
            ->inScope($this->scope->companyId(), $this->scope->locationId());

        // Requesters see their own unless they manage the pipeline.
        if (! $user->hasAnyPermission('development.update', 'development.approve', 'development.assign')) {
            $query->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('assigned_to', $user->id);
            });
        }

        $query
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . strtoupper($request->string('q')) . '%';
                $q->where(fn ($w) => $w->whereRaw('UPPER(REQUEST_NO) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(TITLE) LIKE ?', [$term]));
            })
            ->when($request->filled('status_id'), fn ($q) => $q->where('development_status_id', $request->integer('status_id')))
            ->when($request->filled('approval_status'), fn ($q) => $q->where('approval_status', $request->string('approval_status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')));

        return view('development.index', [
            'requests' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'statuses' => DevelopmentStatus::active()->ordered()->get(),
            'developers' => $this->developers(),
            'filters'  => $request->only(['q', 'status_id', 'approval_status', 'assigned_to']),
        ]);
    }

    public function create(): View
    {
        return view('development.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('development.create'), 403);

        // Requirements and the expected result are what the approver and the
        // developer actually work from, so a request without them is not a request.
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'business_purpose' => ['required', 'string', 'max:20000'],
            'requirements'     => ['required', 'string', 'max:20000'],
            'expected_benefit' => ['required', 'string', 'max:20000'],
            'priority_id'      => ['nullable', 'integer'],
            'target_date'      => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'requirements.required'     => 'Describe what the system needs to do.',
            'expected_benefit.required' => 'Describe the result you expect once this is delivered.',
        ]);

        // The requester's department comes from their record, never from the form.
        $data['department_id'] = $request->user()->department_id;

        $companyId = $this->scope->companyId();
        abort_if($companyId === null, 400, 'Select a company before raising a request.');

        $requestModel = DB::connection('oracle')->transaction(function () use ($data, $request, $companyId) {
            $model = DevelopmentRequest::create($data + [
                'request_no'            => $this->numbers->next(DocumentNumberService::DEVELOPMENT, $companyId),
                'company_id'            => $companyId,
                'location_id'           => $this->scope->locationId(),
                'requester_id'          => $request->user()->id,
                'development_status_id' => DevelopmentStatus::idByCode(DevelopmentStatus::REQUESTED),
                'approval_status'       => DevelopmentRequest::APPROVAL_PENDING,
                'progress_percent'      => 0,
                'created_by'            => $request->user()->id,
                'updated_by'            => $request->user()->id,
            ]);

            $this->audit->created('DEVELOPMENT_REQUEST', $model->id, $model->getAttributes(),
                "Development request {$model->request_no} raised");

            $this->notifications->notifyRoles(
                [Role::IT_MANAGER, Role::DGM],
                $companyId,
                "New development request {$model->request_no}",
                $model->title,
                'DEVELOPMENT_REQUEST',
                $model->id,
                route('development.show', $model->id)
            );

            return $model;
        });

        return redirect()->route('development.show', $requestModel)
            ->with('success', "Request {$requestModel->request_no} submitted.");
    }

    public function show(DevelopmentRequest $development): View
    {
        $this->authoriseAccess($development);

        $development->load(['status', 'priority', 'requester', 'assignee', 'department', 'approver', 'updates.user', 'updates.status']);

        return view('development.show', [
            'request'    => $development,
            'statuses'   => DevelopmentStatus::active()->ordered()->get(),
            'developers' => $this->developers(),
        ]);
    }

    public function update(Request $request, DevelopmentRequest $development): RedirectResponse
    {
        $this->authoriseAccess($development);
        abort_unless($request->user()->hasPermission('development.update'), 403);

        $data = $request->validate([
            'title'                 => ['required', 'string', 'max:255'],
            'business_purpose'      => ['nullable', 'string', 'max:20000'],
            'requirements'          => ['nullable', 'string', 'max:20000'],
            'technical_notes'       => ['nullable', 'string', 'max:20000'],
            'development_status_id' => ['required', 'integer'],
            'priority_id'           => ['nullable', 'integer'],
            'assigned_to'           => ['nullable', 'integer'],
            'target_date'           => ['nullable', 'date'],
            'due_at'                => ['nullable', 'date'],
            'progress_percent'      => ['nullable', 'integer', 'min:0', 'max:100'],
            'estimated_hours'       => ['nullable', 'numeric', 'min:0'],
            'actual_hours'          => ['nullable', 'numeric', 'min:0'],
        ]);

        // Naming a developer is a separate right from editing the request, and the
        // same payload carries both — so it is checked here rather than on the route.
        if (array_key_exists('assigned_to', $data)
            && (int) $data['assigned_to'] !== (int) $development->assigned_to) {
            abort_unless($request->user()->hasPermission('development.assign'), 403,
                'You do not have permission to assign development requests.');

            abort_unless(
                $data['assigned_to'] === null
                    || $this->developers()->contains('id', (int) $data['assigned_to']),
                422,
                'That person cannot be assigned development work for this company.'
            );
        }

        $original = $development->getOriginal();

        DB::connection('oracle')->transaction(function () use ($development, $data, $request, $original) {
            $status = DevelopmentStatus::find($data['development_status_id']);

            // Completing the request stamps the completion time once.
            if ($status?->is_completed_state && $development->completed_at === null) {
                $data['completed_at']     = now();
                $data['progress_percent'] = 100;
            }

            if (! empty($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $development->assigned_to) {
                $data['assigned_by'] = $request->user()->id;
                $data['assigned_at'] = now();
            }

            $development->update($data + ['updated_by' => $request->user()->id]);

            $this->audit->updated('DEVELOPMENT_REQUEST', $development->id, $original, $development->getChanges(),
                "Development request {$development->request_no} updated");

            if ($development->assigned_to) {
                $this->notifications->notify(
                    (int) $development->assigned_to,
                    "Development request {$development->request_no} updated",
                    $development->title,
                    'DEVELOPMENT_REQUEST',
                    $development->id,
                    route('development.show', $development->id)
                );
            }
        });

        return back()->with('success', 'Request updated.');
    }

    /** Approve or reject a request. */
    public function decide(Request $request, DevelopmentRequest $development): RedirectResponse
    {
        $this->authoriseAccess($development);
        abort_unless($request->user()->hasPermission('development.approve'), 403);

        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED,ON_HOLD'],
            'reason'   => ['nullable', 'string', 'max:1000'],
        ]);

        DB::connection('oracle')->transaction(function () use ($development, $data, $request) {
            $development->update([
                'approval_status'  => $data['decision'],
                'approved_by'      => $request->user()->id,
                'approved_at'      => now(),
                'rejection_reason' => $data['decision'] === 'REJECTED' ? ($data['reason'] ?? null) : null,
                'development_status_id' => match ($data['decision']) {
                    'APPROVED' => DevelopmentStatus::idByCode(DevelopmentStatus::APPROVED),
                    'REJECTED' => DevelopmentStatus::idByCode(DevelopmentStatus::REJECTED),
                    default    => DevelopmentStatus::idByCode(DevelopmentStatus::ON_HOLD),
                },
                'updated_by' => $request->user()->id,
            ]);

            $this->audit->log('APPROVAL', 'DEVELOPMENT_REQUEST', $development->id,
                "Request {$development->request_no} marked {$data['decision']}");

            $this->notifications->notify(
                (int) $development->requester_id,
                "Request {$development->request_no} {$data['decision']}",
                $data['reason'] ?? $development->title,
                'DEVELOPMENT_REQUEST',
                $development->id,
                route('development.show', $development->id)
            );
        });

        return back()->with('success', "Request marked {$data['decision']}.");
    }

    /** Add a progress update from the developer. */
    public function addUpdate(Request $request, DevelopmentRequest $development): RedirectResponse
    {
        $this->authoriseAccess($development);

        $data = $request->validate([
            'notes'            => ['required', 'string', 'max:20000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'hours_spent'      => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::connection('oracle')->transaction(function () use ($development, $data, $request) {
            DevelopmentUpdate::create($data + [
                'request_id' => $development->id,
                'user_id'    => $request->user()->id,
                'status_id'  => $development->development_status_id,
            ]);

            $changes = ['updated_by' => $request->user()->id];

            if (isset($data['progress_percent'])) {
                $changes['progress_percent'] = $data['progress_percent'];
            }

            if (isset($data['hours_spent'])) {
                $changes['actual_hours'] = (float) $development->actual_hours + (float) $data['hours_spent'];
            }

            $development->update($changes);

            $this->notifications->notify(
                (int) $development->requester_id,
                "Progress update on {$development->request_no}",
                mb_substr($data['notes'], 0, 200),
                'DEVELOPMENT_REQUEST',
                $development->id,
                route('development.show', $development->id)
            );
        });

        return back()->with('success', 'Update posted.');
    }

    private function authoriseAccess(DevelopmentRequest $model): void
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany((int) $model->company_id), 403,
            'This request belongs to a company you do not have access to.');

        // Mirrors the index filter: without pipeline permissions a user only sees
        // the requests they raised or are working on.
        if (! $user->hasAnyPermission('development.update', 'development.approve', 'development.assign')
            && (int) $model->requester_id !== $user->id
            && (int) $model->assigned_to !== $user->id) {
            abort(403, 'You can only view development requests you raised or are assigned to.');
        }
    }

    private function developers()
    {
        $companyId = $this->scope->companyId();

        return User::active()
            ->whereHas('role', fn ($q) => $q->whereIn('code', [Role::DEVELOPER, Role::IT_MANAGER, Role::IT_STAFF, Role::ADMIN]))
            ->when($companyId, fn ($q) => $q->where(fn ($w) => $w->where('is_all_companies', 1)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId))))
            ->orderBy('name')->get();
    }

    private function formData(): array
    {
        return [
            'priorities' => TicketPriority::active()->ordered()->get(),
            'statuses'   => DevelopmentStatus::active()->ordered()->get(),
            'developers' => $this->developers(),
        ];
    }
}
