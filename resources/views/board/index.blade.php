@extends('layouts.app')

@section('title', 'Board')
@section('subtitle', $sprint
    ? $sprint->name . ' · ' . $sprint->starts_on?->format('d M') . ' – ' . $sprint->ends_on?->format('d M')
    : 'No iteration open for this company yet')

@section('actions')
    <a href="{{ route('board.backlog') }}"
       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">
        Backlog
        @if($backlogSize > 0)
            <span class="ml-1 rounded-full bg-slate-200 px-1.5 text-[11px] font-semibold dark:bg-slate-700">{{ $backlogSize }}</span>
        @endif
    </a>

    {{-- Development requests now ride in the status columns below rather than a
         lane of their own (see BoardService::DEV_COLUMN_MAP) — this is what's
         left of that lane's own link to the full approval pipeline. --}}
    @if($canSeePipeline)
        <a href="{{ route('development.index') }}"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">
            Development pipeline
        </a>
    @endif

    @permission('sprints.manage')
        @if($sprint && ! $sprint->isClosed())
            <form method="POST" action="{{ route('board.sprints.close', $sprint) }}"
                  onsubmit="return confirm('Close {{ $sprint->name }}? Unfinished work goes back to the backlog.');">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">
                    Close iteration
                </button>
            </form>
        @endif
    @endpermission
@endsection

@section('content')

@if($sprint)
    {{-- Where the iteration has got to, in time rather than in points. --}}
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="min-w-0 flex-1">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                <div class="h-full rounded-full bg-brand-500" style="width: {{ $sprint->elapsed_percent }}%"></div>
            </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ $sprint->days_left }} day(s) left
            @if($sprint->goal) &middot; {{ $sprint->goal }} @endif
        </p>
    </div>
@else
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        No iteration is open for this company. One opens automatically overnight, or an IT manager can
        start one from the backlog screen.
    </div>
@endif

{{-- ------------------------------------------------------------ filter bar --}}
@php
    $selectClass = 'rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-800';
    $activeFilters = ($assignedTo ? 1 : 0) + ($priorityId ? 1 : 0) + ($overdueOnly ? 1 : 0) + (count($kinds) ? 1 : 0);
@endphp

<form method="GET" action="{{ route('board.index') }}" id="board-filters"
      class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">

    {{-- Which iteration is on screen travels with every filter change. --}}
    @if($sprint?->id)<input type="hidden" name="sprint_id" value="{{ $sprint->id }}">@endif

    <label class="flex flex-col gap-1">
        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Period</span>
        <select name="cadence" class="{{ $selectClass }}">
            <option value="WEEKLY" @selected($cadence === 'WEEKLY')>This week</option>
            <option value="MONTHLY" @selected($cadence === 'MONTHLY')>This month</option>
        </select>
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Assignee</span>
        <select name="assigned_to" class="{{ $selectClass }}">
            <option value="">Everyone</option>
            <option value="{{ auth()->id() }}" @selected($assignedTo === auth()->id())>Mine only</option>
            @foreach($agents as $agent)
                @if($agent->id !== auth()->id())
                    <option value="{{ $agent->id }}" @selected($assignedTo === $agent->id)>{{ $agent->name }}</option>
                @endif
            @endforeach
        </select>
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Priority</span>
        <select name="priority_id" class="{{ $selectClass }}">
            <option value="">Any priority</option>
            @foreach($priorities as $priority)
                <option value="{{ $priority->id }}" @selected($priorityId === (int) $priority->id)>{{ $priority->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Sort columns by</span>
        <select name="sort" class="{{ $selectClass }}">
            @foreach(\App\Services\BoardService::SORTS as $value => $label)
                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <div class="flex flex-col gap-1">
        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Show</span>
        <div class="flex items-center gap-3 py-1.5">
            @foreach(\App\Services\BoardService::KINDS as $value => $label)
                <label class="flex items-center gap-1.5 text-xs">
                    {{-- Nothing ticked means no type filter, so all three show. --}}
                    <input type="checkbox" name="kinds[]" value="{{ $value }}" @checked(in_array($value, $kinds, true))
                           class="rounded border-slate-300 text-brand-500 dark:border-slate-600 dark:bg-slate-800">
                    {{ $label }}
                </label>
            @endforeach
            <label class="flex items-center gap-1.5 text-xs">
                <input type="checkbox" name="overdue" value="1" @checked($overdueOnly)
                       class="rounded border-slate-300 text-red-500 dark:border-slate-600 dark:bg-slate-800">
                Overdue only
            </label>
        </div>
    </div>

    <div class="ml-auto flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-600">
            Apply
        </button>
        @if($activeFilters || $sort !== \App\Services\BoardService::SORT_MANUAL)
            <a href="{{ route('board.index', array_filter(['sprint_id' => $sprint?->id, 'cadence' => $cadence])) }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">
                Clear
            </a>
        @endif
    </div>

    @if($sort !== \App\Services\BoardService::SORT_MANUAL)
        <p class="w-full text-xs text-amber-700 dark:text-amber-300">
            Columns are sorted by {{ strtolower(\App\Services\BoardService::SORTS[$sort]) }}, so dragging a card still
            moves it between columns but its position within one won't stick — switch back to
            <em>Manual</em> to reorder by hand.
        </p>
    @endif
</form>

{{-- --------------------------------------------------------------- the board --}}
<div id="board" data-move-url="{{ route('board.move') }}" data-can-manage="{{ $canManage ? '1' : '0' }}"
     data-sprint-id="{{ $sprint?->id }}"
     data-manual-order="{{ $sort === \App\Services\BoardService::SORT_MANUAL ? '1' : '0' }}"
     class="flex items-start gap-3 overflow-x-auto pb-4">

    @foreach($statuses as $status)
        @php
            $cards = $columns[$status->id] ?? collect();
        @endphp

        {{-- Always open, even when empty (and even Closed/Cancelled) — a closed
             <details> hides its content from the browser entirely, which makes
             an empty column an impossible drop target for the drag handler.
             A column that starts empty could otherwise never receive its first
             card: cancelling a ticket by dragging it here would have nowhere
             to land. The chevron still lets anyone collapse a noisy column by
             hand; it just never starts that way. --}}
        <details open
                 class="group/col w-72 shrink-0 rounded-xl bg-slate-100/70 dark:bg-slate-800/40">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2.5 select-none">
                <div class="flex items-center gap-2">
                    <x-badge :color="$status->color ?? 'slate'">{{ $status->name }}</x-badge>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $cards->count() }}</span>
                </div>
                <svg class="h-3.5 w-3.5 text-slate-400 transition group-open/col:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.2 7.5 10 12.3l4.8-4.8 1.4 1.4-6.2 6.2-6.2-6.2 1.4-1.4z" clip-rule="evenodd" />
                </svg>
            </summary>

            <div class="board-column min-h-16 space-y-2 px-2 pb-3" data-status-id="{{ $status->id }}">
                @forelse($cards as $card)
                    @php
                        $cardKind = $card instanceof \App\Models\Task ? 'TASK'
                            : ($card instanceof \App\Models\DevelopmentRequest ? 'DEV' : 'TICKET');
                    @endphp
                    <x-board-card :item="$card" :kind="$cardKind" />
                @empty
                    <p class="px-1 py-3 text-xs text-slate-400">Nothing here.</p>
                @endforelse
            </div>
        </details>
    @endforeach
</div>

@if($canManage)
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
        Drag a card between columns to change its status — it records the change, notifies the assignee
        and keeps its history exactly as if you had used its own screen.
        <span class="inline-flex items-center gap-1">
            <x-badge color="indigo" class="!px-1.5 !py-0 !text-[10px]">Dev</x-badge>
            cards read several pipeline stages into one column (e.g. Requested and Analysis both show as
            Open), so dropping one picks that column's specific stage rather than the exact one it left.
        </span>
    </p>
@endif

@endsection
