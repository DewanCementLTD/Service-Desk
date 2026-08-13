@extends('layouts.app')

@section('title', 'Audit Trail')
@section('subtitle', $logs->total() . ' entries')

@section('content')

<form method="GET" class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-6 dark:border-slate-800 dark:bg-slate-900">
    <select name="entity_type" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All entities</option>
        @foreach($entityTypes as $type)
            <option value="{{ $type }}" @selected(($filters['entity_type'] ?? null) === $type)>{{ $type }}</option>
        @endforeach
    </select>

    <select name="action" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All actions</option>
        @foreach($actions as $action)
            <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
        @endforeach
    </select>

    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
           class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
           class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">

    <div class="col-span-2 flex gap-2">
        <button type="submit" class="flex-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Filter</button>
        <a href="{{ route('audit.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">Reset</a>
    </div>
</form>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">When</th>
                    <th class="px-2 py-2.5 text-left font-medium">User</th>
                    <th class="px-2 py-2.5 text-left font-medium">Action</th>
                    <th class="px-2 py-2.5 text-left font-medium">Entity</th>
                    <th class="px-2 py-2.5 text-left font-medium">Description</th>
                    <th class="px-4 py-2.5 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($logs as $log)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5 text-xs whitespace-nowrap text-slate-500">{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-2 py-2.5 text-xs">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="match($log->action) { 'DELETE' => 'red', 'CREATE' => 'green', 'UPDATE' => 'blue', default => 'slate' }">{{ $log->action }}</x-badge></td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                        <td class="px-2 py-2.5 max-w-md truncate text-xs text-slate-600 dark:text-slate-400">{{ $log->description }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('audit.show', $log) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">View</a>
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="6" message="No audit entries match these filters." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $logs->links() }}</div>
    @endif
</x-card>

@endsection
