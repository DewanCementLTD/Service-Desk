@props([
    'label',
    'value' => 0,
    'tone' => 'default',
    'hint' => null,
    'href' => null,
    'icon' => null,
])

@php
    // Written out in full so Tailwind's scanner keeps them in the build.
    $tones = [
        'default' => ['accent' => 'bg-slate-400',   'icon' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',       'value' => 'text-slate-900 dark:text-white'],
        'primary' => ['accent' => 'bg-indigo-500',  'icon' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300', 'value' => 'text-slate-900 dark:text-white'],
        'info'    => ['accent' => 'bg-blue-500',    'icon' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',         'value' => 'text-slate-900 dark:text-white'],
        'success' => ['accent' => 'bg-emerald-500', 'icon' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', 'value' => 'text-slate-900 dark:text-white'],
        'warning' => ['accent' => 'bg-amber-500',   'icon' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',     'value' => 'text-slate-900 dark:text-white'],
        'danger'  => ['accent' => 'bg-rose-500',    'icon' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',         'value' => 'text-rose-600 dark:text-rose-400'],
    ];

    $t   = $tones[$tone] ?? $tones['default'];
    $tag = $href ? 'a' : 'div';

    $display = is_numeric($value)
        ? number_format((float) $value, floor((float) $value) == $value ? 0 : 1)
        : $value;

    // Compact single-glyph marks keep the cards light — no icon font needed.
    $glyphs = [
        'total' => '▤', 'open' => '◔', 'progress' => '◑', 'pending' => '◕',
        'done' => '✓', 'closed' => '⊘', 'overdue' => '⏱', 'sla' => '⚠',
        'critical' => '⬤', 'user' => '☺', 'chart' => '◫', 'clock' => '◷',
    ];
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="group relative overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5 transition dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 {{ $href ? 'hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5' : '' }}">

    {{-- Colour accent bar --}}
    <span class="absolute inset-y-0 left-0 w-1 {{ $t['accent'] }}"></span>

    <div class="flex items-start gap-3 py-4 pl-5 pr-4">
        <div class="min-w-0 flex-1">
            <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-1.5 text-3xl font-bold leading-none tabular-nums {{ $t['value'] }}">{{ $display }}</p>
            @if($hint)
                <p class="mt-1.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
            @endif
        </div>

        @if($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-base {{ $t['icon'] }}">
                {{ $glyphs[$icon] ?? '▤' }}
            </span>
        @endif
    </div>
</{{ $tag }}>
