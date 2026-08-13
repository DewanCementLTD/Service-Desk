@extends('layouts.app')

@section('title', 'Development Dashboard')
@section('subtitle', ($activeCompany?->name ?? 'No company selected'))

@section('content')

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        <x-stat-card label="Total requests"  :value="$stats['total_requests']"      href="{{ route('development.index') }}" />
        <x-stat-card label="In development"  :value="$stats['developing_requests']" tone="primary" />
        <x-stat-card label="Testing / UAT"   :value="$stats['testing_requests']"    tone="info" />
        <x-stat-card label="Delivered"       :value="$stats['completed_requests']"  tone="success" />
        <x-stat-card label="Awaiting approval" :value="$stats['pending_approval']"  tone="warning" />
        <x-stat-card label="Overdue"         :value="$stats['overdue_requests']"    tone="danger" />
        <x-stat-card label="Avg progress"    :value="($stats['avg_progress'] ?? 0) . '%'" />
    </div>

    <x-card title="Pipeline" class="mt-4">
        <x-slot:action>
            <a href="{{ route('development.index') }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">View all</a>
        </x-slot:action>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Request</th>
                        <th class="px-2 py-2 text-left font-medium">Requester</th>
                        <th class="px-2 py-2 text-left font-medium">Developer</th>
                        <th class="px-2 py-2 text-left font-medium">Status</th>
                        <th class="px-2 py-2 text-left font-medium">Approval</th>
                        <th class="px-4 py-2 text-right font-medium">Progress</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($pipeline as $request)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('development.show', $request) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $request->request_no }}</a>
                                <p class="mt-0.5 max-w-sm truncate text-xs text-slate-600 dark:text-slate-400">{{ $request->title }}</p>
                            </td>
                            <td class="px-2 py-2.5 text-xs text-slate-500">{{ $request->requester?->name }}</td>
                            <td class="px-2 py-2.5 text-xs text-slate-500">{{ $request->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-2 py-2.5"><x-badge :color="$request->status?->color">{{ $request->status?->name }}</x-badge></td>
                            <td class="px-2 py-2.5">
                                <x-badge :color="match($request->approval_status) { 'APPROVED' => 'green', 'REJECTED' => 'red', 'ON_HOLD' => 'orange', default => 'slate' }">
                                    {{ $request->approval_status }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ (int) $request->progress_percent }}%"></div>
                                    </div>
                                    <span class="w-8 text-right text-xs tabular-nums text-slate-500">{{ $request->progress_percent }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" message="No development requests yet." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

@endsection
