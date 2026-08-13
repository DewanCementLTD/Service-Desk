@props(['title' => null, 'subtitle' => null, 'action' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    @if($title || $action)
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <div>
                @if($title)
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                @endif
            </div>
            @if($action)
                <div class="shrink-0">{{ $action }}</div>
            @endif
        </header>
    @endif

    <div {{ $attributes->only('body-class')->merge(['class' => '']) }}>
        {{ $slot }}
    </div>
</section>
