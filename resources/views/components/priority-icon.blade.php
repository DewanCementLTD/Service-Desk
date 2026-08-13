{{--
    Jira-style priority mark: bars pointing up for the urgent end of the scale,
    down for the relaxed end. Colour comes from the priority lookup row, so the
    class strings are written out in full for Tailwind's scanner rather than
    constructed from the value.
--}}
@props(['priority' => null, 'class' => 'h-4 w-4'])

@php
    $code  = $priority?->code ?? 'MEDIUM';
    $color = match ($priority?->color) {
        'red'    => 'text-red-600 dark:text-red-400',
        'amber'  => 'text-amber-600 dark:text-amber-400',
        'orange' => 'text-orange-600 dark:text-orange-400',
        'blue'   => 'text-blue-600 dark:text-blue-400',
        'green'  => 'text-green-600 dark:text-green-400',
        default  => 'text-slate-400 dark:text-slate-500',
    };

    // Critical and High point up, Low points down, Medium sits flat.
    $path = match ($code) {
        'CRITICAL' => 'M12 19V6m0 0-5 5m5-5 5 5',
        'HIGH'     => 'M12 18V8m0 0-4 4m4-4 4 4',
        'LOW'      => 'M12 6v10m0 0 4-4m-4 4-4-4',
        default    => 'M5 10h14M5 15h14',
    };
@endphp

<svg {{ $attributes->merge(['class' => $class . ' shrink-0 ' . $color]) }}
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
     stroke-linecap="round" stroke-linejoin="round"
     role="img" aria-label="{{ $priority?->name ?? 'Medium' }} priority">
    <title>{{ $priority?->name ?? 'Medium' }} priority</title>
    <path d="{{ $path }}" />
</svg>
