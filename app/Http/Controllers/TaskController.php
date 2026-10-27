<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskComment;
use App\Models\TaskStatus;
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

class TaskController extends Controller
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

        $query = Task::with(['status', 'priority', 'assignee', 'assigner', 'department', 'assignees.assignee'])
            ->visibleTo($user)
            ->inScope($this->scope->companyId(), $this->scope->locationId());

        // Without assignment rights a user sees only their own tasks — including
        // the ones they only ride along on as a co-assignee.
        if (! $user->hasPermission('tasks.assign')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('assigned_by', $user->id)
                    ->orWhereHas('assignees', fn ($sq) => $sq->where('user_id', $user->id));
            });
        }

        $query
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . strtoupper($request->string('q')) . '%';
                $q->where(fn ($w) => $w->whereRaw('UPPER(TASK_NO) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(TITLE) LIKE ?', [$term]));
            })
            ->when($request->filled('status_id'), fn ($q) => $q->where('task_status_id', $request->integer('status_id')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->forAssignee($request->integer('assigned_to')))
            ->when($request->input('due') === 'overdue', fn ($q) => $q->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereHas('status', fn ($s) => $s->where('is_completed_state', 0)));

        return view('tasks.index', array_merge($this->formData(), [
            'tasks'   => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status_id', 'assigned_to', 'due']),
        ]));
    }

    public function create(): View
    {
        return view('tasks.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tasks.create'), 403);

        $data = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:20000'],
            'priority_id'     => ['nullable', 'integer'],
            'assigned_to'     => ['nullable', 'integer'],
            'assignees'       => ['nullable', 'array', 'max:2'],
            'assignees.*'     => ['integer', 'exists:oracle.users,id'],
            'department_id'   => ['nullable', 'integer'],
            'parent_task_id'  => ['nullable', 'integer'],
            'due_at'          => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $assignees = array_slice($data['assignees'] ?? [], 0, 2);
        unset($data['assignees']);

        // Raising a task is not the same as handing it out. Without assignment
        // rights the task lands on the person who raised it, whoever the form
        // named — a team lead or admin passes it on afterwards. Mirrors the
        // same split in update(), where those fields are stripped rather than
        // rewritten because the task already has an owner by then.
        if (! $request->user()->hasPermission('tasks.assign')) {
            $data['assigned_to'] = $request->user()->id;
            $assignees           = [];
        }

        $companyId = $this->scope->companyId();
        abort_if($companyId === null, 400, 'Select a company first.');

        $task = DB::connection('oracle')->transaction(function () use ($data, $assignees, $request, $companyId) {
            $model = Task::create($data + [
                'task_no'          => $this->numbers->next(DocumentNumberService::TASK, $companyId),
                'company_id'       => $companyId,
                'location_id'      => $this->scope->locationId(),
                'task_status_id'   => TaskStatus::idByCode(TaskStatus::PENDING),
                'assigned_by'      => $request->user()->id,
                'assigned_at'      => ! empty($data['assigned_to']) ? now() : null,
                'progress_percent' => 0,
                'created_by'       => $request->user()->id,
                'updated_by'       => $request->user()->id,
            ]);

            $this->syncAssignees($model, $assignees, $request->user()->id);

            $this->audit->created('TASK', $model->id, $model->getAttributes(), "Task {$model->task_no} created");

            // Nobody needs telling about a task they just raised for themselves,
            // which is now every task an IT staff member creates.
            if ($model->assigned_to && (int) $model->assigned_to !== (int) $request->user()->id) {
                $this->notifications->notify(
                    (int) $model->assigned_to,
                    "Task {$model->task_no} assigned to you",
                    $model->title,
                    'TASK',
                    $model->id,
                    route('tasks.show', $model->id)
                );
            }

            return $model;
        });

        return redirect()->route('tasks.show', $task)->with('success', "Task {$task->task_no} created.");
    }

    public function show(Task $task): View
    {
        $this->authoriseAccess($task);

        $task->load(['status', 'priority', 'assignee', 'assigner', 'department', 'comments.user',
            'subtasks.status', 'assignees.assignee']);

        return view('tasks.show', array_merge($this->formData(), ['task' => $task]));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authoriseAccess($task);
        abort_unless($request->user()->hasPermission('tasks.update'), 403);

        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:20000'],
            'task_status_id'   => ['required', 'integer'],
            'priority_id'      => ['nullable', 'integer'],
            'assigned_to'      => ['nullable', 'integer'],
            'assignees'        => ['nullable', 'array', 'max:2'],
            'assignees.*'      => ['integer', 'exists:oracle.users,id'],
            'department_id'    => ['nullable', 'integer'],
            'due_at'           => ['nullable', 'date'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'actual_hours'     => ['nullable', 'numeric', 'min:0'],
            'remarks'          => ['nullable', 'string', 'max:20000'],
        ]);

        $assignees = array_slice($data['assignees'] ?? [], 0, 2);
        unset($data['assignees']);

        // The person a task was handed to reports on it; they do not get to rewrite
        // it, move its deadline or pass it to somebody else. Only assignment rights
        // carry those powers — co-assignees are a form of passing it on, so the
        // posted list is ignored for the same users whose $data is stripped.
        $canAssign = $request->user()->hasPermission('tasks.assign');

        if (! $canAssign) {
            $data = array_intersect_key($data, array_flip([
                'task_status_id', 'progress_percent', 'actual_hours', 'remarks',
            ]));
        }

        $original = $task->getOriginal();

        DB::connection('oracle')->transaction(function () use ($task, $data, $assignees, $canAssign, $request, $original) {
            $status = TaskStatus::find($data['task_status_id']);

            if ($status?->is_completed_state && $task->completed_at === null) {
                $data['completed_at']     = now();
                $data['completed_by']     = $request->user()->id;
                $data['progress_percent'] = 100;
            }

            if ($status?->code === TaskStatus::IN_PROGRESS && $task->started_at === null) {
                $data['started_at'] = now();
            }

            if (! empty($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $task->assigned_to) {
                $data['assigned_by'] = $request->user()->id;
                $data['assigned_at'] = now();

                $this->notifications->notify(
                    (int) $data['assigned_to'],
                    "Task {$task->task_no} assigned to you",
                    $task->title,
                    'TASK',
                    $task->id,
                    route('tasks.show', $task->id)
                );
            }

            $task->update($data + ['updated_by' => $request->user()->id]);

            if ($canAssign) {
                $this->syncAssignees($task, $assignees, $request->user()->id);
            }

            $this->audit->updated('TASK', $task->id, $original, $task->getChanges(), "Task {$task->task_no} updated");
        });

        return back()->with('success', 'Task updated.');
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $this->authoriseAccess($task);

        $data = $request->validate([
            'body'             => ['required', 'string', 'max:20000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        DB::connection('oracle')->transaction(function () use ($task, $data, $request) {
            TaskComment::create($data + ['task_id' => $task->id, 'user_id' => $request->user()->id]);

            if (isset($data['progress_percent'])) {
                $task->update(['progress_percent' => $data['progress_percent'], 'updated_by' => $request->user()->id]);
            }

            $this->notifications->notifyMany(
                array_filter([$task->assigned_to, $task->assigned_by]),
                "New comment on task {$task->task_no}",
                mb_substr($data['body'], 0, 200),
                'TASK',
                $task->id,
                route('tasks.show', $task->id)
            );
        });

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tasks.delete'), 403,
            'You do not have permission to delete tasks.');

        $this->authoriseAccess($task);

        $taskNo = $task->task_no;
        $task->delete();

        $this->audit->deleted('TASK', $task->id, [],
            "Task {$taskNo} deleted");

        return redirect()->route('tasks.index')
            ->with('success', "Task {$taskNo} has been deleted.");
    }

    public function updatePriority(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('tasks.manage_priority'), 403,
            'You do not have permission to change task priority.');

        $this->authoriseAccess($task);

        $data = $request->validate([
            'priority_id' => ['required', 'integer', 'exists:oracle.ticket_priorities,id'],
        ]);

        $oldPriority = $task->priority?->name;
        $task->update(['priority_id' => $data['priority_id'], 'updated_by' => $request->user()->id]);

        $this->audit->updated('TASK', $task->id,
            ['priority_id' => $oldPriority],
            ['priority_id' => $task->priority?->name],
            "Task {$task->task_no} priority changed to {$task->priority?->name}");

        return back()->with('success', 'Task priority updated.');
    }

    /**
     * Replace a task's co-assignees with the posted list.
     *
     * The primary assignee is dropped from it — they already hold the task via
     * ASSIGNED_TO, and a row here as well would double-count them everywhere
     * that counts assignees (Team Reports especially).
     */
    private function syncAssignees(Task $task, array $userIds, int $actorId): void
    {
        $primary = $task->assigned_to === null ? null : (int) $task->assigned_to;

        $wanted = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn (int $id) => $id === $primary)
            ->unique()
            ->take(2)
            ->values();

        $existing = TaskAssignee::where('task_id', $task->id)->pluck('user_id')
            ->map(fn ($id) => (int) $id);

        TaskAssignee::where('task_id', $task->id)->delete();

        foreach ($wanted as $index => $userId) {
            TaskAssignee::create([
                'task_id'     => $task->id,
                'user_id'     => $userId,
                'sort_order'  => $index,
                'assigned_at' => now(),
                'assigned_by' => $actorId,
            ]);

            // Only tell people who weren't already on it.
            if (! $existing->contains($userId)) {
                $this->notifications->notify(
                    $userId,
                    "Task {$task->task_no} assigned to you",
                    $task->title,
                    'TASK',
                    $task->id,
                    route('tasks.show', $task->id)
                );
            }
        }

        $task->setRelation('assignees', TaskAssignee::with('assignee')
            ->where('task_id', $task->id)->orderBy('sort_order')->get());
    }

    private function authoriseAccess(Task $task): void
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany((int) $task->company_id), 403,
            'This task belongs to a company you do not have access to.');

        // Mirrors the index filter: without assignment rights a user only sees
        // the tasks they own, raised, or are a co-assignee on.
        if (! $user->hasPermission('tasks.assign')
            && (int) $task->assigned_to !== $user->id
            && (int) $task->assigned_by !== $user->id
            && ! $task->assignees()->where('user_id', $user->id)->exists()) {
            abort(403, 'You can only view tasks assigned to you or raised by you.');
        }
    }

    private function formData(): array
    {
        $companyId = $this->scope->companyId();

        return [
            // Drives whether the forms offer assignee pickers at all. store()
            // and update() enforce the same rule server-side regardless.
            'canAssign'   => (bool) auth()->user()?->hasPermission('tasks.assign'),
            'statuses'    => TaskStatus::active()->ordered()->get(),
            'priorities'  => TicketPriority::active()->ordered()->get(),
            'departments' => Department::active()->where('company_id', $companyId)->orderBy('name')->get(),
            'users'       => User::active()
                ->when($companyId, fn ($q) => $q->where(fn ($w) => $w->where('is_all_companies', 1)
                    ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId))))
                ->orderBy('name')->get(),
        ];
    }
}
