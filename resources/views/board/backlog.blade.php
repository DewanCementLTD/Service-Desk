@extends('layouts.app')

@section('title', 'Backlog')
@section('subtitle', 'Everything open that is not committed to an iteration yet.')

@section('actions')
    <a href="{{ route('board.index') }}"
       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Board</a>
@endsection

@section('content')

<div class="space-y-4">

    {{-- ------------------------------------------------------- open an iteration --}}
    @permission('sprints.manage')
        <x-card title="Iterations" subtitle="The current week and month open automatically overnight.">
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($sprints as $iteration)
                    <div class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                        <span class="text-sm font-medium">{{ $iteration->name }}</span>
                        <x-badge :color="$iteration->state === 'ACTIVE' ? 'green' : 'slate'">{{ $iteration->state }}</x-badge>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $iteration->starts_on?->format('d M') }} – {{ $iteration->ends_on?->format('d M Y') }}
                        </span>

                        <div class="ml-auto flex items-center gap-2">
                            @if($iteration->state === 'PLANNED')
                                <form method="POST" action="{{ route('board.sprints.start', $iteration) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium dark:border-slate-700">Start</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('board.sprints.close', $iteration) }}"
                                  onsubmit="return confirm('Close {{ $iteration->name }}? Unfinished work returns here.');">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium dark:border-slate-700">Close</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-slate-500">No iterations yet — one opens automatically, or create it below.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('board.sprints.store') }}"
                  class="grid grid-cols-1 gap-3 border-t border-slate-200 p-4 md:grid-cols-5 dark:border-slate-800">
                @csrf
                <x-field label="Name" name="name">
                    <input name="name" required maxlength="100" placeholder="Week 34 · 2026"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                </x-field>
                <x-field label="Cadence" name="cadence">
                    <select name="cadence" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        <option value="WEEKLY">Weekly</option>
                        <option value="MONTHLY">Monthly</option>
                    </select>
                </x-field>
                <x-field label="Starts" name="starts_on">
                    <input type="date" name="starts_on" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                </x-field>
                <x-field label="Ends" name="ends_on">
                    <input type="date" name="ends_on" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                </x-field>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Create</button>
                </div>
            </form>
        </x-card>
    @endpermission

    {{-- ------------------------------------------------------------- the backlog --}}
    <form method="POST" action="{{ route('board.commit') }}" id="backlog-form">
        @csrf
        <input type="hidden" name="kind" value="TICKET">

        <x-card title="Tickets in the backlog" subtitle="{{ $tickets->total() }} open, uncommitted.">
            @if($canManage && $sprints->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-2.5 dark:border-slate-800">
                    <label class="flex items-center gap-2 text-xs font-medium">
                        <input type="checkbox" id="select-all" class="rounded border-slate-300">
                        Select all on this page
                    </label>

                    <select name="sprint_id" class="ml-auto rounded-lg border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800">
                        @foreach($sprints as $iteration)
                            <option value="{{ $iteration->id }}">{{ $iteration->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">
                        Add selected
                    </button>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            @if($canManage)<th class="w-8 px-3 py-2.5"></th>@endif
                            <th class="px-3 py-2.5 font-semibold">Ticket</th>
                            <th class="px-3 py-2.5 font-semibold">Priority</th>
                            <th class="px-3 py-2.5 font-semibold">Status</th>
                            <th class="px-3 py-2.5 font-semibold">Raised by</th>
                            <th class="px-3 py-2.5 font-semibold">Age</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                @if($canManage)
                                    <td class="px-3 py-2.5">
                                        <input type="checkbox" name="ids[]" value="{{ $ticket->id }}" class="backlog-check rounded border-slate-300">
                                    </td>
                                @endif
                                <td class="px-3 py-2.5">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-300">{{ $ticket->ticket_no }}</a>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($ticket->subject, 70) }}</p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="flex items-center gap-1.5">
                                        <x-priority-icon :priority="$ticket->priority" class="h-4 w-4" />
                                        <span class="text-xs">{{ $ticket->priority?->name }}</span>
                                    </span>
                                </td>
                                <td class="px-3 py-2.5"><x-badge :color="$ticket->status?->color ?? 'slate'">{{ $ticket->status?->name }}</x-badge></td>
                                <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $ticket->requester?->name }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $ticket->created_at?->diffForHumans(null, true) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">The backlog is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $tickets->links() }}</div>
        </x-card>
    </form>

    {{-- --------------------------------------------------- development backlog --}}
    <form method="POST" action="{{ route('board.commit') }}">
        @csrf
        <input type="hidden" name="kind" value="DEV">

        <x-card title="Development requests in the backlog" subtitle="Approved work not yet scheduled.">
            @if($canManage && $sprints->isNotEmpty() && $development->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-2.5 dark:border-slate-800">
                    <select name="sprint_id" class="ml-auto rounded-lg border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800">
                        @foreach($sprints as $iteration)
                            <option value="{{ $iteration->id }}">{{ $iteration->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">Add selected</button>
                </div>
            @endif

            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($development as $request)
                    <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        @if($canManage)
                            <input type="checkbox" name="ids[]" value="{{ $request->id }}" class="rounded border-slate-300">
                        @endif
                        <x-priority-icon :priority="$request->priority" class="h-4 w-4" />
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('development.show', $request) }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">{{ $request->request_no }}</a>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $request->title }}</span>
                        </span>
                        <x-badge :color="$request->status?->color ?? 'slate'">{{ $request->status?->name }}</x-badge>
                    </label>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-slate-500">Nothing waiting.</p>
                @endforelse
            </div>
        </x-card>
    </form>
</div>

<script>
    // Select-all for the ticket backlog; scoped to the page rather than the bundle
    // because it is the only place a checkbox list is committed in bulk.
    (function () {
        var all = document.getElementById('select-all');

        if (! all) {
            return;
        }

        all.addEventListener('change', function () {
            document.querySelectorAll('.backlog-check').forEach(function (box) {
                box.checked = all.checked;
            });
        });
    })();
</script>

@endsection
