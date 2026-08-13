@extends('layouts.app')

@section('title', 'Tasks')
@section('subtitle', $tasks->total() . ' task(s) in ' . ($activeCompany?->name ?? 'current scope'))

@section('actions')
    @permission('tasks.create')
        <a href="{{ route('tasks.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New task</a>
    @endpermission
@endsection

@section('content')

<form method="GET" class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-5 dark:border-slate-800 dark:bg-slate-900">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Task no or title"
           class="col-span-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">

    <select name="status_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All statuses</option>
        @foreach($statuses as $status)
            <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
        @endforeach
    </select>

    <select name="assigned_to" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">Any assignee</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" @selected(($filters['assigned_to'] ?? null) == $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>

    <div class="flex gap-2">
        <button type="submit" class="flex-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Filter</button>
        <a href="{{ route('tasks.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">Reset</a>
    </div>
</form>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Task</th>
                    <th class="px-2 py-2.5 text-left font-medium">Assignee</th>
                    <th class="px-2 py-2.5 text-left font-medium">Priority</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-2 py-2.5 text-right font-medium">Progress</th>
                    <th class="px-4 py-2.5 text-right font-medium">Due</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($tasks as $task)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('tasks.show', $task) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $task->task_no }}</a>
                            <p class="mt-0.5 max-w-sm truncate text-xs text-slate-600 dark:text-slate-400">{{ $task->title }}</p>
                        </td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $task->assignee?->name ?? '—' }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$task->priority?->color">{{ $task->priority?->name ?? '—' }}</x-badge></td>
                        <td class="px-2 py-2.5"><x-badge :color="$task->status?->color">{{ $task->status?->name }}</x-badge></td>
                        <td class="px-2 py-2.5 text-right text-xs tabular-nums">{{ $task->progress_percent }}%</td>
                        <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap {{ $task->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                            {{ $task->due_at?->format('d M Y') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="6" message="No tasks match these filters." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tasks->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $tasks->links() }}</div>
    @endif
</x-card>

@endsection
