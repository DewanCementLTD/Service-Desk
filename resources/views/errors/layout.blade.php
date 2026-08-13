{{--
    Shared shell for error pages.

    Scope violations produce a 403 in normal use — a user opening a link to a
    ticket in a company they were never granted — so these pages have to read as
    an explanation rather than a crash.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') &middot; {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css'])
    <script>
        (function () {
            var stored = localStorage.getItem('sd-theme');
            var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>
<body class="app-surface flex h-full items-center justify-center p-6 text-slate-900 antialiased dark:text-slate-100">

<main class="w-full max-w-md text-center">
    <x-brand-mark class="mx-auto h-12 w-12" />

    <p class="mt-6 font-mono text-sm font-semibold text-brand-600 dark:text-brand-300">@yield('code')</p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">@yield('heading')</h1>
    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">@yield('message')</p>

    <div class="mt-7 flex items-center justify-center gap-2">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium transition hover:bg-white dark:border-slate-700 dark:hover:bg-slate-800">
            Go back
        </a>
        <a href="{{ route('dashboard') }}"
           class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
            Dashboard
        </a>
    </div>

    {{-- Only the messages the application itself passes to abort() are shown, and
         never on a 5xx, where the text comes from the failure rather than from us. --}}
    @php
        $detail = ($exception ?? null)?->getMessage();
        $status = (int) trim($__env->yieldContent('code'));
    @endphp

    @if($detail && $status < 500 && mb_strlen($detail) < 200)
        <p class="mt-6 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
            {{ $detail }}
        </p>
    @endif
</main>

</body>
</html>
