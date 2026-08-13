@extends('layouts.app')

@section('title', 'Raise a request')
@section('subtitle', 'Pick the one that fits — it decides who picks it up and how it is tracked.')

@section('content')

<div class="grid max-w-4xl grid-cols-1 gap-4 md:grid-cols-2">

    {{-- ------------------------------------------------------ bugs & issues --}}
    <a href="{{ route('tickets.create') }}"
       class="group flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-400 hover:shadow-raised dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-500">
        <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 9v4m0 4h.01M10.3 3.9 2.4 17.4A1.9 1.9 0 0 0 4 20.3h16a1.9 1.9 0 0 0 1.6-2.9L13.7 3.9a1.9 1.9 0 0 0-3.4 0Z" />
            </svg>
        </span>

        <h2 class="text-base font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">Bugs &amp; Issues</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Something that used to work has stopped, is wrong, or is blocking you — an ERP error,
            a network or connectivity problem, or anything else the IT team needs to fix.
        </p>

        <ul class="mt-3 space-y-1 text-xs text-slate-500 dark:text-slate-400">
            <li>· Goes straight to the IT queue with a response deadline</li>
            <li>· Use this when you need something repaired</li>
        </ul>

        <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 dark:text-brand-300">
            Report a problem
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg>
        </span>
    </a>

    {{-- --------------------------------------------- new development request --}}
    @if($canRaiseDevelopment)
        <a href="{{ route('development.create') }}"
           class="group flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-400 hover:shadow-raised dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-500">
            <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m8 16-4-4 4-4m8 0 4 4-4 4m-2-10-4 12" />
                </svg>
            </span>

            <h2 class="text-base font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">New Development Request</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Something that does not exist yet — a new report, a new screen, or a change to how
                a system works today.
            </p>

            <ul class="mt-3 space-y-1 text-xs text-slate-500 dark:text-slate-400">
                <li>· Reviewed and approved before any work starts</li>
                <li>· You will be asked for your requirements and the result you expect</li>
            </ul>

            <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 dark:text-brand-300">
                Request something new
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg>
            </span>
        </a>
    @endif
</div>

@endsection
