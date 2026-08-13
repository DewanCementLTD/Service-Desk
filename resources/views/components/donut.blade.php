@props(['segments' => [], 'total' => null, 'label' => 'Total'])

{{--
    Inline SVG donut. Segments are [label, value, colour-name]; drawn with
    stroke-dasharray on concentric circles so no charting library is needed.
--}}
@php
    $palette = [
        'blue'   => '#3b82f6', 'green'  => '#10b981', 'amber'  => '#f59e0b',
        'red'    => '#ef4444', 'purple' => '#a855f7', 'slate'  => '#94a3b8',
        'indigo' => '#6366f1', 'cyan'   => '#06b6d4', 'orange' => '#f97316',
    ];

    $sum   = $total ?? collect($segments)->sum(fn ($s) => (float) $s['value']);
    $sum   = max(0.000001, (float) $sum);
    $r     = 42;
    $circ  = 2 * M_PI * $r;
    $offset = 0;
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-5']) }}>
    <div class="relative shrink-0">
        <svg viewBox="0 0 100 100" class="h-32 w-32 -rotate-90">
            <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="currentColor"
                    class="text-slate-100 dark:text-slate-800" stroke-width="12"></circle>

            @foreach($segments as $segment)
                @php
                    $value  = (float) $segment['value'];
                    $length = $value / $sum * $circ;
                    $colour = $palette[$segment['color'] ?? 'slate'] ?? $palette['slate'];
                @endphp
                @if($value > 0)
                    <circle cx="50" cy="50" r="{{ $r }}" fill="none"
                            stroke="{{ $colour }}" stroke-width="12"
                            stroke-dasharray="{{ round($length, 2) }} {{ round($circ - $length, 2) }}"
                            stroke-dashoffset="{{ round(-$offset, 2) }}"></circle>
                    @php $offset += $length; @endphp
                @endif
            @endforeach
        </svg>

        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($sum == 0.000001 ? 0 : $sum) }}</span>
            <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ $label }}</span>
        </div>
    </div>

    <ul class="min-w-0 flex-1 space-y-1.5">
        @foreach($segments as $segment)
            <li class="flex items-center gap-2 text-sm">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $palette[$segment['color'] ?? 'slate'] ?? $palette['slate'] }}"></span>
                <span class="min-w-0 flex-1 truncate text-slate-600 dark:text-slate-400">{{ $segment['label'] }}</span>
                <span class="shrink-0 font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format((float) $segment['value']) }}</span>
            </li>
        @endforeach
    </ul>
</div>
