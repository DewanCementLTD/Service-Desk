@extends('layouts.app')

@section('title', 'Board')
@section('subtitle', $sprint
    ? $sprint->name . ' · ' . $sprint->starts_on?->format('d M') . ' – ' . $sprint->ends_on?->format('d M')
    : 'No iteration open for this company yet')

@section('actions')
    {{-- Personal filter — a link rather than JS so it survives a reload and can
         be bookmarked, and costs nothing to keep correct. --}}
    <a href="{{ route('board.index', array_filter(['cadence' => $cadence, 'sprint_id' => $sprint?->id, 'mine' => $mineOnly ? null : 1])) }}"
       class="rounded-lg border px-3 py-1.5 text-xs font-medium transition
              {{ $mineOnly ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-500/10 dark:text-brand-300'
                            : 'border-slate-300 dark:border-slate-700' }}">
        {{ $mineOnly ? 'Showing mine only' : 'Assigned to me' }}
    </a>

    <a href="{{ route('board.backlog') }}"
       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">
        Backlog
        @if($backlogSize > 0)
            <span class="ml-1 rounded-full bg-slate-200 px-1.5 text-[11px] font-semibold dark:bg-slate-700">{{ $backlogSize }}</span>
        @endif
    </a>

    <form method="GET" class="flex items-center gap-2">
        @if($mineOnly)<input type="hidden" name="mine" value="1">@endif
        <select name="cadence" onchange="this.form.submit()"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-800">
            <option value="WEEKLY" @selected($cadence === 'WEEKLY')>This week</option>
            <option value="MONTHLY" @selected($cadence === 'MONTHLY')>This month</option>
        </select>
    </form>

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

{{-- --------------------------------------------------------------- the board --}}
<div id="board" data-move-url="{{ route('board.move') }}" data-can-manage="{{ $canManage ? '1' : '0' }}"
     data-sprint-id="{{ $sprint?->id }}"
     class="flex items-start gap-3 overflow-x-auto pb-4">

    @foreach($statuses as $status)
        @php
            $cards    = $columns[$status->id] ?? collect();
            // Finished work stays out of the way until someone asks for it — a
            // week's worth of Closed tickets is not something you need to scroll
            // past to see what is still open.
            $collapse = $status->is_closed_state && $cards->isEmpty();
        @endphp

        <details @if(! $collapse) open @endif
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
                    <x-board-card :item="$card" :kind="$card instanceof \App\Models\Task ? 'TASK' : 'TICKET'" />
                @empty
                    <p class="px-1 py-3 text-xs text-slate-400">Nothing here.</p>
                @endforelse
            </div>
        </details>
    @endforeach

    {{-- Development requests keep their own lane: they run an approval pipeline
         rather than the ticket statuses, so they are not draggable between the
         columns above. --}}
    <details open class="group/col w-72 shrink-0 rounded-xl bg-indigo-50/70 dark:bg-indigo-500/5">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2.5 select-none">
            <div class="flex items-center gap-2">
                <x-badge color="indigo">Development</x-badge>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $development->count() }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if($canSeePipeline)
                    <a href="{{ route('development.index') }}"
                       class="text-[11px] font-semibold text-brand-600 hover:underline dark:text-brand-300"
                       onclick="event.stopPropagation()">
                        Full pipeline →
                    </a>
                @endif
                <svg class="h-3.5 w-3.5 text-slate-400 transition group-open/col:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.2 7.5 10 12.3l4.8-4.8 1.4 1.4-6.2 6.2-6.2-6.2 1.4-1.4z" clip-rule="evenodd" />
                </svg>
            </div>
        </summary>

        <div class="board-column min-h-16 space-y-2 px-2 pb-3" data-lane="DEV">
            @forelse($development as $request)
                <x-board-card :item="$request" kind="DEV" />
            @empty
                <p class="px-1 py-3 text-xs text-slate-400">Nothing in this iteration.</p>
            @endforelse
        </div>
    </details>
</div>

@if($canManage)
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
        Drag a card between columns to change its status — the ticket records the change, notifies the
        assignee and keeps its history exactly as if you had used the ticket screen.
    </p>
@endif

@endsection
