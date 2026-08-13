{{--
    One card on the board. `kind` tells the drag handler which register the id
    belongs to; everything else is display.

    Deliberately spare: the column already says whether this is a ticket, task
    or development request and which status it is in, so the card itself
    carries only what changes from one card to the next — title, number,
    category, priority, SLA state when it is not simply "on track", and who
    has it. Tasks are shown exactly like tickets — no distinguishing badge —
    they just have no category to print, the same as a development request.
--}}
@props(['item', 'kind' => 'TICKET'])

@php
    $isTicket = $kind === 'TICKET';
    $number   = match ($kind) {
        'TICKET' => $item->ticket_no,
        'TASK'   => $item->task_no,
        default  => $item->request_no,
    };
    $title    = match ($kind) {
        'TICKET' => $item->subject,
        'TASK'   => $item->title,
        default  => $item->title,
    };
    $url      = match ($kind) {
        'TICKET' => route('tickets.show', $item->id),
        'TASK'   => route('tasks.show', $item->id),
        default  => route('development.show', $item->id),
    };

    $sla = $isTicket ? match ($item->sla_status) {
        'BREACHED' => ['red', 'Breached'],
        'AT_RISK'  => ['amber', 'At risk'],
        'PAUSED'   => ['slate', 'Paused'],
        default    => null,
    } : null;
@endphp

<article data-board-card data-kind="{{ $kind }}" data-id="{{ $item->id }}"
         class="group cursor-grab rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm transition hover:border-brand-300 hover:shadow active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800">

    <div class="flex items-start justify-between gap-2">
        <a href="{{ $url }}" class="min-w-0 text-sm font-medium leading-snug text-slate-900 hover:underline dark:text-white">
            {{ \Illuminate\Support\Str::limit($title, 80) }}
        </a>
        <x-priority-icon :priority="$item->priority" class="h-3.5 w-3.5 mt-0.5" />
    </div>

    <div class="mt-2 flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
        <span class="font-mono">{{ $number }}</span>
        @if($isTicket && $item->category)
            <span aria-hidden="true">·</span>
            <span class="truncate">{{ $item->category->name }}</span>
        @endif

        @if($sla)
            <x-badge :color="$sla[0]" class="!px-1.5 !py-0 !text-[10px]">{{ $sla[1] }}</x-badge>
        @endif

        <span class="ml-auto" title="{{ $item->assignee?->name ?? 'Unassigned' }}">
            @if($item->assignee)
                <x-user-avatar :user="$item->assignee" class="h-5 w-5" text="text-[9px]" />
            @else
                <span class="flex h-5 w-5 items-center justify-center rounded-full border border-dashed border-slate-300 text-slate-400 dark:border-slate-600">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" /></svg>
                </span>
            @endif
        </span>
    </div>
</article>
