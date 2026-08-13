<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Match the dashboard's theme choice before first paint.
        (function () {
            var stored = localStorage.getItem('sd-theme');
            var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>
<body class="h-full bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">

<div class="flex min-h-full">

    {{-- ------------------------------------------------------- brand panel --}}
    <div class="relative hidden w-1/2 overflow-hidden bg-slate-950 lg:block xl:w-[55%]">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-700 via-brand-800 to-slate-950"></div>
        <div class="grid-overlay absolute inset-0 opacity-60"></div>
        <div class="absolute -left-24 top-1/3 h-96 w-96 rounded-full bg-brand-500/30 blur-3xl"></div>
        <div class="absolute -bottom-24 right-0 h-96 w-96 rounded-full bg-sky-400/20 blur-3xl"></div>

        <div class="relative flex h-full flex-col justify-between p-10 xl:p-14">
            <div class="flex items-center gap-3">
                <x-brand-mark class="h-10 w-10" />
                <span class="text-sm font-semibold tracking-wide text-white">{{ config('app.name') }}</span>
            </div>

            <div class="max-w-md">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white xl:text-4xl">
                    Every request, tracked to closure.
                </h2>
                <p class="mt-4 text-sm leading-relaxed text-indigo-100/80">
                    Tickets, development requests and management tasks — one system, one audit
                    trail, live figures for every company and location.
                </p>

                <ul class="mt-10 space-y-4">
                    @foreach([
                        ['SLA on the clock', 'Deadlines derived from priority or policy, refreshed automatically.'],
                        ['Scoped access', 'Users see only the companies and locations they are granted.'],
                        ['Full audit trail', 'Every change recorded with who, what and when.'],
                    ] as [$heading, $detail])
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 text-[11px] font-bold text-white">&checkmark;</span>
                            <div>
                                <p class="text-sm font-medium text-white">{{ $heading }}</p>
                                <p class="text-xs leading-relaxed text-indigo-100/70">{{ $detail }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-xs text-indigo-200/50">
                &copy; {{ date('Y') }} {{ config('app.name') }}. Authorised users only.
            </p>
        </div>
    </div>

    {{-- -------------------------------------------------------- form panel --}}
    <div class="flex w-full flex-col justify-center px-5 py-10 sm:px-10 lg:w-1/2 xl:w-[45%]">
        <div class="mx-auto w-full max-w-sm">

            {{-- Small-screen brand header (the panel above is hidden there). --}}
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <x-brand-mark class="h-10 w-10" />
                <span class="text-base font-semibold">{{ config('app.name') }}</span>
            </div>

            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Welcome back</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                Sign in with your service desk account to continue.
            </p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf

                @if(session('error'))
                    <div class="flex gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 10a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="flex gap-2.5 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 10a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div>
                    <label for="login" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                        Mobile number or email
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3 4a2 2 0 00-2 2v.4l9 4.6 9-4.6V6a2 2 0 00-2-2H3z" />
                                <path d="M19 8.6l-8.6 4.4a1 1 0 01-.9 0L1 8.6V14a2 2 0 002 2h14a2 2 0 002-2V8.6z" />
                            </svg>
                        </span>
                        <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                               autocomplete="username" placeholder="3001234567 or you@company.com"
                               class="field-input pl-10">
                    </div>
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                        Password
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <input id="password" name="password" type="password" required
                               autocomplete="current-password" placeholder="••••••••"
                               class="field-input pl-10 pr-11">
                        <button type="button" id="toggle-password"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                aria-label="Show password">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10 4C5.5 4 2 8.5 1 10c1 1.5 4.5 6 9 6s8-4.5 9-6c-1-1.5-4.5-6-9-6zm0 10a4 4 0 110-8 4 4 0 010 8zm0-2a2 2 0 100-4 2 2 0 000 4z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                        <input type="checkbox" name="remember" value="1"
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                        Keep me signed in
                    </label>
                    <span class="text-xs text-slate-400 dark:text-slate-500">Password reset? Contact IT</span>
                </div>

                <button type="submit"
                        class="group flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white shadow-raised transition hover:bg-brand-700 active:scale-[0.99]">
                    Sign in
                    <svg class="h-4 w-4 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h9.6l-3.3-3.3a1 1 0 111.4-1.4l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4L13.6 11H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-slate-400 dark:text-slate-500">
                Access is logged. Use of this system is restricted to authorised staff.
            </p>
        </div>
    </div>
</div>

<script>
    document.getElementById('toggle-password').addEventListener('click', function () {
        var field = document.getElementById('password');
        var shown = field.type === 'text';
        field.type = shown ? 'password' : 'text';
        this.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
        this.classList.toggle('text-brand-600', !shown);
    });
</script>

</body>
</html>
