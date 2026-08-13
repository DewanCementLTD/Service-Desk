@extends('layouts.app')

@section('title', 'Development Requests')
@section('subtitle', $requests->total() . ' request(s) in ' . ($activeCompany?->name ?? 'current scope'))

@section('actions')
    @permission('development.create')
        <a href="{{ route('development.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New request</a>
    @endpermission
@endsection

@section('content')

<form method="GET" class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-5 dark:border-slate-800 dark:bg-slate-900">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Request no or title"
           class="col-span-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">

    <select name="status_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All statuses</option>
        @foreach($statuses as $status)
            <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
        @endforeach
    </select>

    <select name="approval_status" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">Any approval</option>
        @foreach(['PENDING', 'APPROVED', 'REJECTED', 'ON_HOLD'] as $state)
            <option value="{{ $state }}" @selected(($filters['approval_status'] ?? null) === $state)>{{ $state }}</option>
        @endforeach
    </select>

    <div class="flex gap-2">
        <button type="submit" class="flex-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Filter</button>
        <a href="{{ route('development.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">Reset</a>
    </div>
</form>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Request</th>
                    <th class="px-2 py-2.5 text-left font-medium">Requester</th>
                    <th class="px-2 py-2.5 text-left font-medium">Developer</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-2 py-2.5 text-left font-medium">Approval</th>
                    <th class="px-2 py-2.5 text-right font-medium">Progress</th>
                    <th class="px-4 py-2.5 text-right font-medium">Due</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($requests as $request)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('development.show', $request) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $request->request_no }}</a>
                            <p class="mt-0.5 max-w-sm truncate text-xs text-slate-600 dark:text-slate-400">{{ $request->title }}</p>
                        </td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $request->requester?->name }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $request->assignee?->name ?? '—' }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$request->status?->color">{{ $request->status?->name }}</x-badge></td>
                        <td class="px-2 py-2.5">
                            <x-badge :color="match($request->approval_status) { 'APPROVED' => 'green', 'REJECTED' => 'red', 'ON_HOLD' => 'orange', default => 'slate' }">
                                {{ $request->approval_status }}
                            </x-badge>
                        </td>
                        <td class="px-2 py-2.5 text-right text-xs tabular-nums">{{ $request->progress_percent }}%</td>
                        <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap {{ $request->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                            {{ $request->due_at?->format('d M Y') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="7" message="No development requests match these filters." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($requests->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $requests->links() }}</div>
    @endif
</x-card>

@endsection
