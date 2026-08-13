@extends('layouts.app')

@section('title', 'Management Dashboard')
@section('subtitle', ($activeCompany?->name ?? 'No company selected') . ($activeLocation ? ' · ' . $activeLocation->name : ' · All locations'))

@section('content')

    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Tasks</h2>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat-card label="Total tasks"  :value="$taskStats['total_tasks']"       href="{{ route('tasks.index') }}" />
        <x-stat-card label="Completed"    :value="$taskStats['completed_tasks']"   tone="success" />
        <x-stat-card label="In progress"  :value="$taskStats['in_progress_tasks']" tone="primary" />
        <x-stat-card label="Pending"      :value="$taskStats['pending_tasks']"     tone="warning" />
        <x-stat-card label="Overdue"      :value="$taskStats['overdue_tasks']"     tone="danger" href="{{ route('tasks.index', ['due' => 'overdue']) }}" />
        <x-stat-card label="Critical"     :value="$taskStats['critical_tasks']"    tone="danger" />
    </div>

    <h2 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Service desk &amp; delivery</h2>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
        <x-stat-card label="Tickets"        :value="$ticketStats['total_tickets']"       href="{{ route('tickets.index') }}" />
        <x-stat-card label="Ticket overdue" :value="$ticketStats['overdue_tickets']"     tone="danger" />
        <x-stat-card label="SLA breached"   :value="$ticketStats['sla_breached_tickets']" tone="danger" />
        <x-stat-card label="Dev requests"   :value="$devStats['total_requests']"         href="{{ route('development.index') }}" />
        <x-stat-card label="Awaiting approval" :value="$devStats['pending_approval']"    tone="warning" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <x-card title="Recent tasks">
            <x-slot:action>
                <a href="{{ route('tasks.index') }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">View all</a>
            </x-slot:action>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($recentTasks as $task)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('tasks.show', $task) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $task->task_no }}</a>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $task->title }}</p>
                                </td>
                                <td class="px-2 py-2.5 text-xs text-slate-500">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="px-2 py-2.5"><x-badge :color="$task->status?->color">{{ $task->status?->name }}</x-badge></td>
                                <td class="px-4 py-2.5 text-right text-xs tabular-nums text-slate-500">{{ $task->progress_percent }}%</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="4" message="No tasks yet." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Overdue tasks">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($overdueTasks as $task)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('tasks.show', $task) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $task->task_no }}</a>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $task->title }}</p>
                                </td>
                                <td class="px-2 py-2.5 text-xs text-slate-500">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="px-4 py-2.5 text-right text-xs font-medium text-red-600 whitespace-nowrap">{{ $task->due_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="3" message="Nothing overdue." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    @if(count($companyBreakdown) > 1)
        <x-card title="Across your companies" class="mt-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Company</th>
                            <th class="px-2 py-2 text-right font-medium">Tickets</th>
                            <th class="px-2 py-2 text-right font-medium">Active</th>
                            <th class="px-4 py-2 text-right font-medium">Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($companyBreakdown as $company)
                            @php $row = array_change_key_case((array) $company, CASE_LOWER); @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $row['company_name'] }}</td>
                                <td class="px-2 py-2 text-right tabular-nums">{{ $row['total_tickets'] }}</td>
                                <td class="px-2 py-2 text-right tabular-nums">{{ $row['active_tickets'] }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-red-600">{{ $row['overdue_tickets'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

@endsection
