@extends('layouts.app')

@section('title', 'Service Desk Overview')

@php
    // Derived headline figures — everything below comes from the live Oracle
    // aggregates in $stats, nothing is hard-coded.
    $total     = (int) $stats['total_tickets'];
    $closed    = (int) $stats['closed_tickets'] + (int) $stats['resolved_tickets'];
    $active    = max(0, $total - $closed);
    $breached  = (int) $stats['sla_breached_tickets'];
    $slaHealth = $total > 0 ? round((1 - $breached / $total) * 100) : 100;
    $closeRate = $total > 0 ? round($closed / $total * 100) : 0;

    // Status/priority ids behind the KPI tiles below, so each one can link
    // straight to the matching filtered ticket list.
    $inProgressStatusId = \App\Models\TicketStatus::idByCode(\App\Models\TicketStatus::IN_PROGRESS);
    $pendingStatusId    = \App\Models\TicketStatus::idByCode(\App\Models\TicketStatus::PENDING);
    $resolvedStatusId   = \App\Models\TicketStatus::idByCode(\App\Models\TicketStatus::RESOLVED);
    $closedStatusId     = \App\Models\TicketStatus::idByCode(\App\Models\TicketStatus::CLOSED);
    $criticalPriorityId = \App\Models\TicketPriority::where('is_critical', 1)->value('id');
@endphp

@section('hero')
<section class="relative mb-5 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 px-6 py-6 text-white shadow-lg shadow-indigo-600/20">
    {{-- Soft decorative glow, purely visual --}}
    <span aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></span>
    <span aria-hidden="true" class="pointer-events-none absolute -bottom-28 right-32 h-56 w-56 rounded-full bg-violet-400/20 blur-3xl"></span>

    <div class="relative flex flex-wrap items-end justify-between gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-200">Service Desk Overview</p>
            <h1 class="mt-1.5 text-2xl font-bold tracking-tight sm:text-3xl">{{ $activeCompany?->name ?? 'No company selected' }}</h1>
            <p class="mt-1 text-sm text-indigo-100">
                {{ $activeLocation?->name ?? 'All locations' }}
                @if($activeOrganization) &middot; {{ $activeOrganization->name }} @endif
                &middot; {{ now()->format('D, d M Y') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-6">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wider text-indigo-200">SLA health</p>
                <p class="mt-0.5 text-3xl font-bold tabular-nums">{{ $slaHealth }}%</p>
                <div class="mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-white/20">
                    <div class="h-full rounded-full bg-emerald-300" style="width: {{ $slaHealth }}%"></div>
                </div>
            </div>
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wider text-indigo-200">Closure rate</p>
                <p class="mt-0.5 text-3xl font-bold tabular-nums">{{ $closeRate }}%</p>
                <div class="mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-white/20">
                    <div class="h-full rounded-full bg-sky-300" style="width: {{ $closeRate }}%"></div>
                </div>
            </div>
            <div class="hidden sm:block">
                <p class="text-[11px] font-medium uppercase tracking-wider text-indigo-200">Avg resolution</p>
                <p class="mt-0.5 text-3xl font-bold tabular-nums">
                    {{ $stats['avg_resolution_hours'] !== null ? number_format($stats['avg_resolution_hours'], 1) : '—' }}<span class="text-base font-medium text-indigo-200">h</span>
                </p>
            </div>
            @permission('reports.view')
                <a href="{{ route('reports.tickets') }}"
                   class="rounded-lg bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-inset ring-white/25 backdrop-blur transition hover:bg-white/25">
                    View reports
                </a>
            @endpermission
        </div>
    </div>
</section>
@endsection

@section('content')

{{-- ------------------------------------------------------------ kpi cards --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
    <x-stat-card label="Total tickets" :value="$stats['total_tickets']" icon="total"
                 hint="{{ $active }} still active" href="{{ route('tickets.index') }}" />
    <x-stat-card label="Open" :value="$stats['open_tickets']" tone="info" icon="open"
                 href="{{ route('tickets.index') }}" />
    <x-stat-card label="In progress" :value="$stats['in_progress_tickets']" tone="primary" icon="progress"
                 href="{{ route('tickets.index', ['status_id' => $inProgressStatusId]) }}" />
    <x-stat-card label="Pending" :value="$stats['pending_tickets']" tone="warning" icon="pending"
                 href="{{ route('tickets.index', ['status_id' => $pendingStatusId]) }}" />
    <x-stat-card label="Resolved" :value="$stats['resolved_tickets']" tone="success" icon="done"
                 href="{{ route('tickets.index', ['status_id' => $resolvedStatusId]) }}" />

    <x-stat-card label="Closed" :value="$stats['closed_tickets']" icon="closed"
                 href="{{ route('tickets.index', ['status_id' => $closedStatusId]) }}" />
    <x-stat-card label="Overdue" :value="$stats['overdue_tickets']" tone="danger" icon="overdue"
                 href="{{ route('tickets.index', ['sla' => 'overdue']) }}" />
    <x-stat-card label="SLA breached" :value="$stats['sla_breached_tickets']" tone="danger" icon="sla"
                 href="{{ route('tickets.index', ['sla' => 'breached']) }}" />
    <x-stat-card label="Critical open" :value="$stats['critical_tickets']" tone="danger" icon="critical"
                 href="{{ route('tickets.index', ['priority_id' => $criticalPriorityId]) }}" />
    <x-stat-card label="Unassigned" :value="$stats['unassigned_tickets']" tone="warning" icon="user"
                 href="{{ route('tickets.index', ['sla' => 'unassigned']) }}" />
</div>

{{-- ------------------------------------------------------- trend + donuts --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <x-card class="xl:col-span-2">
        <x-slot:title>Ticket volume</x-slot:title>
        <x-slot:subtitle>Created per day over the last 14 days</x-slot:subtitle>
        <x-slot:action>
            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                <span class="h-2 w-2 rounded-full bg-indigo-500"></span> Created
            </span>
        </x-slot:action>

        @php
            $points = collect($trend)->map(fn ($p) => array_change_key_case((array) $p, CASE_LOWER));
            $peak   = max(1, $points->max(fn ($p) => (int) $p['created_count']));
        @endphp

        <div class="flex h-52 items-end gap-1.5 px-5 pb-4 pt-6">
            @foreach($points as $point)
                @php
                    $created = (int) $point['created_count'];
                    $height  = max(3, round($created / $peak * 100));
                    $isToday = $loop->last;
                @endphp
                {{-- h-full is required: the bar's percentage height needs a definite parent. --}}
                <div class="group/bar relative flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                    <span class="pointer-events-none absolute -top-5 rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-semibold text-white opacity-0 transition group-hover/bar:opacity-100 dark:bg-slate-700">
                        {{ $created }}
                    </span>
                    <div class="w-full rounded-t-md bg-gradient-to-t {{ $isToday ? 'from-violet-500 to-violet-400' : 'from-indigo-600 to-indigo-400' }} transition group-hover/bar:brightness-110"
                         style="height: {{ $height }}%"></div>
                    <span class="text-[9px] font-medium text-slate-400">{{ \Illuminate\Support\Str::substr($point['day_label'], 8) }}</span>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card title="Status mix">
        @php
            $statusSegments = collect($byStatus)
                ->map(fn ($s) => array_change_key_case((array) $s, CASE_LOWER))
                ->filter(fn ($s) => (int) $s['total'] > 0)
                ->map(fn ($s) => ['label' => $s['name'], 'value' => $s['total'], 'color' => $s['color']])
                ->values()->all();
        @endphp

        <div class="p-5">
            @if($statusSegments === [])
                <x-empty-state message="No tickets in this scope yet." />
            @else
                <x-donut :segments="$statusSegments" label="Tickets" />
            @endif
        </div>
    </x-card>
</div>

{{-- ------------------------------------------------------ priority + lists --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">

    <x-card title="Priority mix">
        @php
            $prioritySegments = collect($byPriority)
                ->map(fn ($p) => array_change_key_case((array) $p, CASE_LOWER))
                ->filter(fn ($p) => (int) $p['total'] > 0)
                ->map(fn ($p) => ['label' => $p['name'], 'value' => $p['total'], 'color' => $p['color']])
                ->values()->all();
        @endphp

        <div class="p-5">
            @if($prioritySegments === [])
                <x-empty-state message="No tickets in this scope yet." />
            @else
                <x-donut :segments="$prioritySegments" label="Tickets" />
            @endif
        </div>
    </x-card>

    <x-card title="Recent tickets" class="xl:col-span-2">
        <x-slot:action>
            <a href="{{ route('tickets.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">View all →</a>
        </x-slot:action>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentTickets as $ticket)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="py-2.5 pl-5 pr-2">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $ticket->ticket_no }}</a>
                                <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $ticket->subject }}</p>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ $ticket->requester?->initials }}
                                    </span>
                                    <span class="truncate text-xs text-slate-600 dark:text-slate-400">{{ $ticket->requester?->name }}</span>
                                </div>
                            </td>
                            <td class="px-2 py-2.5"><x-badge :color="$ticket->priority?->color">{{ $ticket->priority?->name }}</x-badge></td>
                            <td class="px-2 py-2.5"><x-badge :color="$ticket->status?->color">{{ $ticket->status?->name }}</x-badge></td>
                            <td class="py-2.5 pl-2 pr-5 text-right text-xs whitespace-nowrap text-slate-500">{{ $ticket->created_at?->diffForHumans(short: true) }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="5" message="No tickets in this scope yet." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>

{{-- ------------------------------------------------------- sla + team + dept --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">

    <x-card>
        <x-slot:title>SLA breaches</x-slot:title>
        <x-slot:subtitle>Tickets past their resolution deadline</x-slot:subtitle>
        <x-slot:action>
            <x-badge color="red">{{ $stats['sla_breached_tickets'] }} total</x-badge>
        </x-slot:action>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($slaBreaches as $ticket)
                        <tr class="transition hover:bg-rose-50/50 dark:hover:bg-rose-500/5">
                            <td class="py-2.5 pl-5 pr-2">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $ticket->ticket_no }}</a>
                                <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $ticket->subject }}</p>
                            </td>
                            <td class="px-2 py-2.5 text-xs text-slate-500">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="py-2.5 pl-2 pr-5 text-right">
                                <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                    {{ $ticket->due_at?->diffForHumans(short: true) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="3" message="No SLA breaches. Nicely done." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="IT team performance" subtitle="Workload and throughput per agent">
        @php $topLoad = max(1, collect($team)->max(fn ($a) => (int) array_change_key_case((array) $a, CASE_LOWER)['assigned_tickets'])); @endphp

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($team as $agent)
                @php
                    $row  = array_change_key_case((array) $agent, CASE_LOWER);
                    $done = (int) $row['completed_tickets'];
                    $all  = max(1, (int) $row['assigned_tickets']);
                @endphp
                <li class="flex items-center gap-3 px-5 py-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        {{ \Illuminate\Support\Str::of($row['agent_name'])->explode(' ')->take(2)->map(fn($p) => mb_substr($p, 0, 1))->implode('') }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $row['agent_name'] }}</p>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="h-1.5 w-full max-w-32 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($done / $all * 100) }}%"></div>
                            </div>
                            <span class="text-[11px] text-slate-400">{{ $done }}/{{ $row['assigned_tickets'] }} closed</span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        @if((int) $row['overdue_tickets'] > 0)
                            <x-badge color="red">{{ $row['overdue_tickets'] }} overdue</x-badge>
                        @else
                            <x-badge color="green">On track</x-badge>
                        @endif
                        <p class="mt-1 text-[11px] text-slate-400">avg {{ $row['avg_resolution_hours'] ?? '—' }}h</p>
                    </div>
                </li>
            @empty
                <x-empty-state message="No assigned tickets yet." />
            @endforelse
        </ul>
    </x-card>
</div>

{{-- --------------------------------------------------- departments + company --}}
<div class="mt-4 grid grid-cols-1 gap-4 {{ count($companyBreakdown) > 1 ? 'xl:grid-cols-2' : '' }}">

    <x-card title="Ticket load by department">
        @php $deptPeak = max(1, collect($departments)->max(fn ($d) => (int) array_change_key_case((array) $d, CASE_LOWER)['total_tickets'])); @endphp

        <ul class="space-y-3 p-5">
            @forelse($departments as $dept)
                @php $row = array_change_key_case((array) $dept, CASE_LOWER); @endphp
                <li>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="truncate font-medium text-slate-700 dark:text-slate-300">{{ $row['department_name'] }}</span>
                        <span class="ml-3 shrink-0 tabular-nums text-slate-500">
                            {{ $row['total_tickets'] }}
                            @if((int) $row['overdue_tickets'] > 0)
                                <span class="ml-1 font-semibold text-rose-600">· {{ $row['overdue_tickets'] }} overdue</span>
                            @endif
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500"
                             style="width: {{ round((int) $row['total_tickets'] / $deptPeak * 100) }}%"></div>
                    </div>
                </li>
            @empty
                <x-empty-state message="No department activity yet." />
            @endforelse
        </ul>
    </x-card>

    @if(count($companyBreakdown) > 1)
        <x-card title="Across your companies" subtitle="You have access to more than one company">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                        <tr>
                            <th class="py-2 pl-5 pr-2 text-left font-medium">Company</th>
                            <th class="px-2 py-2 text-right font-medium">Tickets</th>
                            <th class="px-2 py-2 text-right font-medium">Active</th>
                            <th class="py-2 pl-2 pr-5 text-right font-medium">Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($companyBreakdown as $company)
                            @php $row = array_change_key_case((array) $company, CASE_LOWER); @endphp
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="py-2.5 pl-5 pr-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $row['company_name'] }}</span>
                                    <span class="ml-1.5 font-mono text-[11px] text-slate-400">{{ $row['company_code'] }}</span>
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $row['total_tickets'] }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $row['active_tickets'] }}</td>
                                <td class="py-2.5 pl-2 pr-5 text-right font-semibold tabular-nums text-rose-600">{{ $row['overdue_tickets'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>

{{-- ------------------------------------------------------------- tasks --}}
@php
    $showTasks = auth()->user()->hasPermission('tasks.view');
@endphp

@if($showTasks)
    <div class="mt-6 flex items-center gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tasks</h2>
        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3">
        <x-stat-card label="Tasks total" :value="$taskStats['total_tasks']" href="{{ route('tasks.index') }}" />
        <x-stat-card label="Tasks open" :value="$taskStats['pending_tasks'] + $taskStats['in_progress_tasks']" tone="primary"
                     href="{{ route('tasks.index') }}" />
        <x-stat-card label="Tasks overdue" :value="$taskStats['overdue_tasks']" tone="danger" href="{{ route('tasks.index') }}" />
    </div>

    <div class="mt-4">
        <x-card title="Recent tasks" subtitle="Latest assignments in this scope">
            <x-slot:action>
                <a href="{{ route('tasks.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400">View all →</a>
            </x-slot:action>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($recentTasks as $task)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="py-2.5 pl-5 pr-2">
                                    <a href="{{ route('tasks.show', $task) }}" class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $task->task_no }}</a>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $task->title }}</p>
                                </td>
                                <td class="px-2 py-2.5 text-xs text-slate-500">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="px-2 py-2.5"><x-badge :color="$task->status?->color">{{ $task->status?->name }}</x-badge></td>
                                <td class="py-2.5 pl-2 pr-5 text-right text-xs whitespace-nowrap {{ $task->isOverdue() ? 'font-medium text-rose-600' : 'text-slate-500' }}">
                                    {{ $task->due_at?->format('d M Y') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="4" message="No tasks in this scope yet." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endif

@endsection
