@extends('layouts.app')

@section('title', 'New Task')

@section('content')

<form method="POST" action="{{ route('tasks.store') }}" class="max-w-4xl space-y-4">
    @csrf

    <x-card title="Task details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Title" name="title" :required="true" class="md:col-span-2">
                <input name="title" value="{{ old('title') }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Description" name="description" class="md:col-span-2">
                <textarea name="description" rows="5"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('description') }}</textarea>
            </x-field>

            {{-- Without assignment rights the task is raised against the person
                 creating it; TaskController::store() sets that whatever is posted,
                 so offering a picker here would only mislead. --}}
            @if($canAssign)
            <x-field label="Assign to" name="assigned_to">
                <select name="assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">Unassigned</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Additional Assignees (up to 2 more)" name="assignees[]" class="md:col-span-2">
                <select name="assignees[]" multiple class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" title="Select up to 2 additional assignees">
                    @php $selectedAssignees = array_map('intval', (array) old('assignees', [])); @endphp
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(in_array((int) $user->id, $selectedAssignees, true))>{{ $user->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Hold Ctrl/Cmd to select multiple (up to 2 additional assignees allowed)</p>
            </x-field>
            @else
            <x-field label="Assigned to" name="assigned_to">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                    {{ auth()->user()->name }}
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Tasks you raise are assigned to you. A team lead or administrator can pass this on later.
                </p>
            </x-field>
            @endif

            <x-field label="Priority" name="priority_id">
                <select name="priority_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($priorities as $priority)
                        <option value="{{ $priority->id }}" @selected(old('priority_id') == $priority->id)>{{ $priority->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Department" name="department_id">
                <select name="department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Due date" name="due_at">
                <input type="date" name="due_at" value="{{ old('due_at') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Estimated hours" name="estimated_hours">
                <input type="number" step="0.5" min="0" name="estimated_hours" value="{{ old('estimated_hours') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create task</button>
        <a href="{{ route('tasks.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>
    </div>
</form>

@endsection
