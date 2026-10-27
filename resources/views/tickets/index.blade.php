@extends('layouts.app')

@section('title', 'Tickets')
@section('subtitle', $tickets->total() . ' ticket(s) in ' . ($activeCompany?->name ?? 'current scope'))

@section('actions')
    @permission('reports.export')
        <a href="{{ route('reports.tickets.export', request()->only(['from', 'to'])) }}"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Export CSV</a>
    @endpermission
    @permission('tickets.create')
        <a href="{{ route('tickets.new') }}" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">Raise a request</a>
    @endpermission
@endsection

@section('content')

    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-4 xl:grid-cols-7 dark:border-slate-800 dark:bg-slate-900">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ticket no or subject"
               class="col-span-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">

        <select name="status_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>

        <select name="priority_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">All priorities</option>
            @foreach($priorities as $priority)
                <option value="{{ $priority->id }}" @selected(($filters['priority_id'] ?? null) == $priority->id)>{{ $priority->name }}</option>
            @endforeach
        </select>

        <select name="category_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>

        <select name="assigned_to" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Any assignee</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" @selected(($filters['assigned_to'] ?? null) == $agent->id)>{{ $agent->name }}</option>
            @endforeach
        </select>

        <select name="sla" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Any SLA state</option>
            <option value="overdue" @selected(($filters['sla'] ?? null) === 'overdue')>Overdue</option>
            <option value="breached" @selected(($filters['sla'] ?? null) === 'breached')>SLA breached</option>
            <option value="unassigned" @selected(($filters['sla'] ?? null) === 'unassigned')>Unassigned</option>
        </select>

        <div class="col-span-2 flex gap-2 md:col-span-1">
            <button type="submit" class="flex-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Filter</button>
            <a href="{{ route('tickets.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">Reset</a>
        </div>
    </form>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium">Ticket</th>
                        <th class="px-2 py-2.5 text-left font-medium">Requester</th>
                        <th class="px-2 py-2.5 text-left font-medium">Assignee</th>
                        <th class="px-2 py-2.5 text-left font-medium">Priority</th>
                        <th class="px-2 py-2.5 text-left font-medium">Status</th>
                        <th class="px-2 py-2.5 text-left font-medium">SLA</th>
                        <th class="px-4 py-2.5 text-right font-medium">Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $ticket->ticket_no }}</a>
                                <p class="mt-0.5 max-w-sm truncate text-xs text-slate-600 dark:text-slate-400">{{ $ticket->subject }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ $ticket->location?->name ?? $ticket->company?->name }}
                                    @if($ticket->department) &middot; {{ $ticket->department->name }} @endif
                                </p>
                            </td>
                            <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $ticket->requester?->name }}</td>
                            <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td class="px-2 py-2.5"><x-badge :color="$ticket->priority?->color">{{ $ticket->priority?->name }}</x-badge></td>
                            <td class="px-2 py-2.5"><x-badge :color="$ticket->status?->color">{{ $ticket->status?->name }}</x-badge></td>
                            <td class="px-2 py-2.5">
                                <x-badge :color="match($ticket->sla_status) { 'BREACHED' => 'red', 'AT_RISK' => 'amber', 'MET' => 'green', 'PAUSED' => 'slate', default => 'blue' }">
                                    {{ str_replace('_', ' ', $ticket->sla_status) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap {{ $ticket->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                                {{ $ticket->due_at?->format('d M Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="7" message="No tickets match these filters." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $tickets->links() }}</div>
        @endif
    </x-card>

    {{-- ----------------------------------------------- development requests --}}
    {{-- A development request is tracked in its own pipeline (see the Development
         screen — status, approval, assignment all live there, untouched by this
         block), but it is still work raised against the team, so a snapshot of
         what's still open shows up here too rather than only on a screen most
         IT staff never had a reason to open before. --}}
    @if($developmentRequests !== null)
        <x-card title="Development requests" subtitle="Open requests raised against the team." class="mt-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium">Request</th>
                            <th class="px-2 py-2.5 text-left font-medium">Requester</th>
                            <th class="px-2 py-2.5 text-left font-medium">Assignee</th>
                            <th class="px-2 py-2.5 text-left font-medium">Priority</th>
                            <th class="px-2 py-2.5 text-left font-medium">Status</th>
                            <th class="px-4 py-2.5 text-right font-medium">Raised</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($developmentRequests as $devRequest)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('development.show', $devRequest) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $devRequest->request_no }}</a>
                                    <p class="mt-0.5 max-w-sm truncate text-xs text-slate-600 dark:text-slate-400">{{ $devRequest->title }}</p>
                                </td>
                                <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $devRequest->requester?->name }}</td>
                                <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $devRequest->assignee?->name ?? '—' }}</td>
                                <td class="px-2 py-2.5"><x-badge :color="$devRequest->priority?->color">{{ $devRequest->priority?->name }}</x-badge></td>
                                <td class="px-2 py-2.5"><x-badge :color="$devRequest->status?->color">{{ $devRequest->status?->name }}</x-badge></td>
                                <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap text-slate-500">
                                    {{ $devRequest->created_at?->diffForHumans(null, true) }} ago
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" message="No open development requests." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-2.5 text-right dark:border-slate-800">
                <a href="{{ route('development.index') }}" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-300">
                    View all in Development &rarr;
                </a>
            </div>
        </x-card>
    @endif

@endsection
