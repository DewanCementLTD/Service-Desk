@extends('layouts.app')

@section('title', $task->task_no)
@section('subtitle', $task->title)

@section('actions')
    <a href="{{ route('tasks.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Back to list</a>

    @permission('tasks.delete')
        <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline" onsubmit="return confirm('Delete task {{ $task->task_no }}? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-500/10">Delete task</button>
        </form>
    @endpermission
@endsection

@section('content')

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="space-y-4 xl:col-span-2">

        <x-card title="Description">
            <div class="px-4 py-3 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $task->description ?: '—' }}</div>
        </x-card>

        @permission('tasks.update')
            <x-card title="Update task">
                <form method="POST" action="{{ route('tasks.update', $task) }}" class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    {{-- Assignment rights decide what is editable here. Without
                         them TaskController::update() keeps only the status,
                         progress, hours and remarks and drops the rest, so the
                         other fields are shown as read-only rather than as
                         inputs that quietly do nothing. The title still has to
                         be posted — update() validates it as required. --}}
                    @permission('tasks.assign')
                    <x-field label="Title" name="title" :required="true" class="md:col-span-2">
                        <input name="title" value="{{ old('title', $task->title) }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>
                    @else
                    <input type="hidden" name="title" value="{{ $task->title }}">
                    @endpermission

                    <x-field label="Status" name="task_status_id" :required="true">
                        <select name="task_status_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}" @selected($task->task_status_id == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    @permission('tasks.assign')
                    <x-field label="Primary Assignee" name="assigned_to">
                        <select name="assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <option value="">Unassigned</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected($task->assigned_to == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Additional Assignees (up to 2 more)" name="assignees[]" class="md:col-span-2">
                        <select name="assignees[]" multiple class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" title="Select up to 2 additional assignees">
                            @php
                                $selectedAssignees = collect(old('assignees', $task->assignees->pluck('user_id')))
                                    ->map(fn ($id) => (int) $id)->all();
                            @endphp
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(in_array((int) $user->id, $selectedAssignees, true))>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Hold Ctrl/Cmd to select multiple (up to 2 additional assignees allowed)</p>
                    </x-field>

                    <x-field label="Due date" name="due_at">
                        <input type="date" name="due_at" value="{{ old('due_at', $task->due_at?->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>
                    @else
                    <x-field label="Assignees" name="assigned_to" class="md:col-span-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                            {{ collect([$task->assignee?->name])
                                ->concat($task->assignees->map(fn ($a) => $a->assignee?->name))
                                ->filter()->unique()->join(', ') ?: 'Unassigned' }}
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Only a team lead or administrator can change who a task is assigned to.</p>
                    </x-field>

                    <x-field label="Due date" name="due_at">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                            {{ $task->due_at?->format('d M Y') ?: '—' }}
                        </div>
                    </x-field>
                    @endpermission

                    <x-field label="Progress %" name="progress_percent">
                        <input type="number" name="progress_percent" min="0" max="100" value="{{ old('progress_percent', $task->progress_percent) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Actual hours" name="actual_hours">
                        <input type="number" step="0.5" min="0" name="actual_hours" value="{{ old('actual_hours', $task->actual_hours) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Remarks" name="remarks">
                        <input name="remarks" value="{{ old('remarks', $task->remarks) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
                    </div>
                </form>
            </x-card>
        @endpermission

        <x-card title="Comments">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($task->comments as $comment)
                    <div class="px-4 py-3">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="text-sm font-medium">{{ $comment->user?->name }}</span>
                            <span class="ml-auto text-xs text-slate-400">{{ $comment->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $comment->body }}</p>
                    </div>
                @empty
                    <x-empty-state message="No comments yet." />
                @endforelse
            </div>

            <form method="POST" action="{{ route('tasks.comment', $task) }}" class="border-t border-slate-200 p-4 dark:border-slate-800">
                @csrf
                <textarea name="body" rows="3" required placeholder="Add a comment…"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                <div class="mt-2 flex items-center gap-2">
                    <input type="number" name="progress_percent" min="0" max="100" placeholder="Progress %"
                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <button type="submit" class="ml-auto rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">Post</button>
                </div>
            </form>
        </x-card>
    </div>

    <div class="space-y-4">
        <x-card title="Details">
            <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @foreach([
                    'Status'     => $task->status?->name,
                    'Priority'   => $task->priority?->name,
                    'Assignee'   => $task->assignee?->name ?? 'Unassigned',
                    'Assigned by'=> $task->assigner?->name,
                    'Department' => $task->department?->name,
                    'Due'        => $task->due_at?->format('d M Y'),
                    'Started'    => $task->started_at?->format('d M Y'),
                    'Completed'  => $task->completed_at?->format('d M Y'),
                    'Progress'   => $task->progress_percent . '%',
                    'Estimated'  => $task->estimated_hours ? $task->estimated_hours . ' h' : null,
                    'Actual'     => $task->actual_hours ? $task->actual_hours . ' h' : null,
                ] as $label => $value)
                    <div class="flex items-center justify-between gap-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="text-right text-slate-700 dark:text-slate-300">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        @permission('tasks.manage_priority')
            <x-card title="Change Priority">
                <form method="POST" action="{{ route('tasks.priority', $task) }}" class="p-4">
                    @csrf
                    <div class="space-y-3">
                        <select name="priority_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <option value="">Select priority...</option>
                            @foreach($priorities as $priority)
                                <option value="{{ $priority->id }}" @selected($task->priority_id == $priority->id)>{{ $priority->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Update Priority</button>
                    </div>
                </form>
            </x-card>
        @endpermission

        @if($task->subtasks->isNotEmpty())
            <x-card title="Subtasks">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($task->subtasks as $subtask)
                        <li class="flex items-center justify-between gap-2 px-4 py-2 text-sm">
                            <a href="{{ route('tasks.show', $subtask) }}" class="truncate text-indigo-600 dark:text-indigo-400">{{ $subtask->title }}</a>
                            <x-badge :color="$subtask->status?->color">{{ $subtask->status?->name }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</div>

@endsection
