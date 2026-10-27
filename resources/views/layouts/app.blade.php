<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

    {{-- Identity: tab icon, home-screen icon, install manifest and link previews.
         The SVG covers modern tabs; the ICO/PNGs cover everything else. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="description" content="@yield('meta_description', 'Raise and track IT tickets and development requests across Yousuf Dewan Companies.')">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="@yield('title', 'Dashboard') · {{ config('app.name') }}">
    <meta property="og:description" content="@yield('meta_description', 'Raise and track IT tickets and development requests.')">
    <meta property="og:image" content="{{ asset('icon-512.png') }}">
    <meta name="twitter:card" content="summary">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Applied before first paint so the page never flashes the wrong theme.
        (function () {
            var stored = localStorage.getItem('sd-theme');
            var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>
<body class="app-surface h-full text-slate-900 antialiased dark:text-slate-100">

@php
    $user = auth()->user();

    // Icons are inline paths so the sidebar carries no extra requests.
    //
    // The permission on each item is what puts it in the sidebar, and it is not
    // always the permission that opens the page. Development and Tasks are gated
    // on the *management* rights rather than on `.view`, because an employee who
    // may raise a development request reaches it through the ticket-type chooser
    // and should not carry a second section in their navigation for it.
    $nav = [
        ['label' => 'Dashboard',   'route' => 'dashboard',        'active' => 'dashboard*',   'permission' => 'dashboard.view',
         'icon' => 'M3 10.5 12 3l9 7.5M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5'],
        ['label' => 'Tickets',     'route' => 'tickets.index',    'active' => 'tickets*',     'permission' => 'tickets.view',
         'icon' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V7ZM14 5v14'],
        // 'Dashboard' above sends these roles to their operational dashboard
        // (dgm/management/development), not the personal view of their own
        // tickets and tasks — this is the only way back to that view.
        ...($user->hasRole(\App\Models\Role::DGM, \App\Models\Role::ADMIN, \App\Models\Role::IT_MANAGER, \App\Models\Role::MANAGEMENT, \App\Models\Role::DEVELOPER) ? [[
            'label' => 'My dashboard', 'route' => 'dashboard.personal', 'active' => 'dashboard/personal*',
            'permission' => 'dashboard.view',
            'icon' => 'M4 4h7v7H4V4Zm9 0h7v4h-7V4Zm0 7h7v9h-7v-9ZM4 14h7v6H4v-6Z',
        ]] : []),
        ['label' => 'Board',       'route' => 'board.index',      'active' => 'board*',       'permission' => 'board.view',
         'icon' => 'M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm10 0a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5Z'],
        // Matches the route's own gate (permission:tasks.view). Keying this on
        // assign/create hid the register from everyone who only works tasks
        // rather than handing them out — IT staff and developers included, who
        // get tasks assigned to them and could not reach them. index() already
        // narrows the list to a person's own tasks without tasks.assign.
        ['label' => 'Tasks',       'route' => 'tasks.index',      'active' => 'tasks*',
         'permission' => 'tasks.view',
         'icon' => 'M9 5h10M9 12h10M9 19h10M4.5 5l1 1 2-2m-3 8 1 1 2-2m-3 8 1 1 2-2'],
        ...($user->hasPermission('team.manage') ? [[
            'label' => 'Team',       'route' => 'team-lead.index',  'active' => 'team[^-]*',
            'permission' => 'team.manage',
            'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 7.121-3.072M15 19.128A9.38 9.38 0 0 1 9.375 19.5m0 0a9.37 9.37 0 0 0-7.128-3.072M15 19.128v-.008a9.38 9.38 0 0 0-7.128-3.072m0 0a3 3 0 1 1 6 0m7.072-4.049a.75.75 0 1 0-1.061-1.061 3.75 3.75 0 1 1 1.06 1.061m0 0a9.382 9.382 0 0 0-5.858-9.977m11.129-2.111a9.375 9.375 0 1 1-13.25 13.25m12.331-9.355a3 3 0 1 1-4.243-4.243m2.122 6.364a6 6 0 1 0-8.485 8.485m3.182-3.182a3 3 0 1 1-4.243-4.243'
        ],
        [
            'label' => 'Team Reports', 'route' => 'team-reports.index', 'active' => 'team-reports*',
            'permission' => 'team.manage',
            'icon' => 'm9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m-6 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m0 0V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2z'
        ]] : []),
        ['label' => 'Reports',     'route' => 'reports.index',    'active' => 'reports*',     'permission' => 'reports.view',
         'icon' => 'M5 20V10m7 10V4m7 16v-7'],
        // Everyone can sign in, so everyone gets the guide - it filters itself by role.
        ['label' => 'User guide',  'route' => 'guide',            'active' => 'guide*',       'permission' => 'dashboard.view',
         'icon' => 'M12 7c0-1.7 1.6-3 3.5-3S19 5.3 19 7v10c0-1.7-1.6-3-3.5-3S12 15.3 12 17m0-10c0-1.7-1.6-3-3.5-3S5 5.3 5 7v10c0-1.7 1.6-3 3.5-3s3.5 1.3 3.5 3m0-10v10'],
    ];

    // Development requests already show on the Board's own lane, so a separate
    // sidebar section is only added for someone who approves them but has no
    // board to reach the pipeline from — Management, not IT management.
    if ($user->hasAnyPermission('development.approve', 'development.assign', 'development.update')
        && ! $user->hasPermission('board.view')) {
        array_splice($nav, 2, 0, [[
            'label' => 'Development', 'route' => 'development.index', 'active' => 'development*',
            'permission' => ['development.approve', 'development.assign', 'development.update'],
            'icon' => 'm8 16-4-4 4-4m8 0 4 4-4 4m-2-10-4 12',
        ]]);
    }

    $admin = [
        ['label' => 'Users',         'route' => 'users.index',              'active' => 'users*',              'permission' => 'users.view',
         'icon' => 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm11 9v-1a4 4 0 0 0-3-3.9M16 3.6a4 4 0 0 1 0 7.7'],
        ['label' => 'Roles',         'route' => 'admin.roles.index',        'active' => 'admin/roles*',        'permission' => 'roles.manage',
         'icon' => 'M12 3 4 6v6c0 4.5 3.2 7.9 8 9 4.8-1.1 8-4.5 8-9V6l-8-3ZM9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z'],
        ['label' => 'Organisations', 'route' => 'admin.organizations.index','active' => 'admin/organizations*','permission' => 'organizations.manage',
         'icon' => 'M4 21V6a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v15M13 21V10h6a1 1 0 0 1 1 1v10M4 21h17M7 9h3M7 13h3M7 17h3M16 14h1M16 18h1'],
        ['label' => 'Companies',     'route' => 'admin.companies.index',    'active' => 'admin/companies*',    'permission' => 'companies.manage',
         'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 11h.01M15 11h.01'],
        ['label' => 'Locations',     'route' => 'admin.locations.index',    'active' => 'admin/locations*',    'permission' => 'locations.manage',
         'icon' => 'M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
        ['label' => 'Departments',   'route' => 'admin.departments.index',  'active' => 'admin/departments*',  'permission' => 'departments.manage',
         'icon' => 'M12 3v4M6 21v-4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4M4 7h16v4H4zM4 21h4M16 21h4'],
        ['label' => 'Audit trail',   'route' => 'audit.index',              'active' => 'audit*',              'permission' => 'audit.view',
         'icon' => 'M9 3h6l1 2h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h3l1-2Zm-1 9 2.5 2.5L16 9'],
    ];

    $showAdmin = $user->hasAnyPermission('users.view', 'roles.manage', 'organizations.manage', 'companies.manage', 'locations.manage', 'departments.manage', 'audit.view');
@endphp

{{-- The nav list is a partial so the desktop rail and the mobile drawer can
     never drift apart. --}}

<div class="flex min-h-full">

    {{-- --------------------------------------------------- desktop sidebar --}}
    {{-- Pinned to the viewport rather than the document: without sticky+h-screen
         here, the aside stretches to match the (often much taller) content column
         and scrolls away with the page instead of staying put. --}}
    <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200/80 bg-white/80 backdrop-blur lg:sticky lg:top-0 lg:flex lg:h-screen dark:border-slate-800 dark:bg-slate-900/80">
        <div class="flex h-16 items-center gap-2.5 border-b border-slate-200/80 px-4 dark:border-slate-800">
            <x-brand-mark class="h-9 w-9" />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold leading-tight">{{ config('app.name') }}</p>
                <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $activeCompany?->name ?? 'No company' }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            @include('layouts.partials.nav-items', ['items' => $nav])

            @if($showAdmin)
                <p class="px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Administration</p>
                @include('layouts.partials.nav-items', ['items' => $admin])
            @endif
        </nav>

        <div class="border-t border-slate-200/80 p-3 dark:border-slate-800">
            <div class="flex items-center gap-2.5 rounded-lg px-2 py-2">
                <x-user-avatar :user="$user" class="h-8 w-8" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium leading-tight">{{ $user->name }}</p>
                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $user->role?->name }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit"
                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-slate-500 transition hover:bg-red-50 hover:text-red-600 dark:text-slate-400 dark:hover:bg-red-500/10 dark:hover:text-red-300">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 17v1a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v1M19 12H9m10 0-3-3m3 3-3 3" />
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- ---------------------------------------------------- mobile drawer --}}
    <div id="mobile-nav" class="fixed inset-0 z-40 hidden lg:hidden">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" data-close-nav></div>
        <aside class="absolute inset-y-0 left-0 flex w-72 flex-col border-r border-slate-200 bg-white shadow-raised dark:border-slate-800 dark:bg-slate-900">
            <div class="flex h-16 items-center gap-2.5 border-b border-slate-200 px-4 dark:border-slate-800">
                <x-brand-mark class="h-9 w-9" />
                <span class="flex-1 truncate text-sm font-semibold">{{ config('app.name') }}</span>
                <button type="button" data-close-nav aria-label="Close menu"
                        class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                @include('layouts.partials.nav-items', ['items' => $nav])

                @if($showAdmin)
                    <p class="px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Administration</p>
                    @include('layouts.partials.nav-items', ['items' => $admin])
                @endif
            </nav>

            <div class="border-t border-slate-200 p-3 dark:border-slate-800">
                <div class="flex items-center gap-2.5 px-2 py-2">
                    <x-user-avatar :user="$user" class="h-8 w-8" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium leading-tight">{{ $user->name }}</p>
                        <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $user->role?->name }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-slate-500 transition hover:bg-red-50 hover:text-red-600 dark:text-slate-400 dark:hover:bg-red-500/10 dark:hover:text-red-300">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 17v1a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v1M19 12H9m10 0-3-3m3 3-3 3" />
                        </svg>
                        Sign out
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <div class="flex min-w-0 flex-1 flex-col">

        {{-- --------------------------------------------------------- top bar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-slate-200/80 bg-white/85 px-3 backdrop-blur sm:px-4 dark:border-slate-800 dark:bg-slate-900/85">

            <button type="button" id="open-nav" aria-label="Open menu"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>

            {{-- Company / location scope switcher --}}
            <form method="POST" action="{{ route('scope.company') }}" class="flex items-center">
                @csrf
                <label class="sr-only" for="scope-company">Company</label>
                <select id="scope-company" name="company_id" onchange="this.form.submit()"
                        class="max-w-36 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium sm:max-w-48 dark:border-slate-700 dark:bg-slate-800">
                    @forelse($availableCompanies ?? [] as $company)
                        <option value="{{ $company->id }}" @selected($activeCompany?->id === $company->id)>
                            {{ $company->name }}
                        </option>
                    @empty
                        <option>No company access</option>
                    @endforelse
                </select>
            </form>

            <form method="POST" action="{{ route('scope.location') }}" class="hidden items-center sm:flex">
                @csrf
                <label class="sr-only" for="scope-location">Location</label>
                <select id="scope-location" name="location_id" onchange="this.form.submit()"
                        class="max-w-44 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All locations</option>
                    @foreach($availableLocations ?? [] as $location)
                        <option value="{{ $location->id }}" @selected($activeLocation?->id === $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">

                {{-- Theme toggle --}}
                <button type="button" id="theme-toggle" aria-label="Toggle dark mode"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg class="h-[18px] w-[18px] dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
                    </svg>
                    <svg class="hidden h-[18px] w-[18px] dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4" />
                    </svg>
                </button>

                {{-- Notifications — badge and list are refreshed by a poll, and
                     the same notifications arrive as browser push when enabled. --}}
                <div class="relative" id="notification-bell">
                    <button type="button" id="notification-button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false"
                            class="relative rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 9a6 6 0 1 0-12 0c0 4-2 5-2 5h16s-2-1-2-5M10.5 19a2 2 0 0 0 3 0" />
                        </svg>
                        <span id="notification-badge"
                              class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white ring-2 ring-white dark:ring-slate-900 {{ ($unreadNotificationCount ?? 0) > 0 ? '' : 'hidden' }}">
                            {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                        </span>
                    </button>

                    <div id="notification-panel"
                         class="absolute right-0 z-50 mt-2 hidden w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-raised dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Notifications</span>
                            <form method="POST" action="{{ route('notifications.readAll') }}">
                                @csrf
                                <button type="submit" class="text-[11px] font-semibold text-brand-600 hover:underline dark:text-brand-300">Mark all read</button>
                            </form>
                        </div>

                        <ul id="notification-list" class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                            <li class="px-3 py-6 text-center text-xs text-slate-400">Loading…</li>
                        </ul>

                        <div id="notification-push" class="border-t border-slate-200 px-3 py-2.5 dark:border-slate-800">
                            <label class="flex items-center justify-between gap-3 text-xs font-medium text-slate-600 dark:text-slate-300">
                                <span>Browser notifications</span>
                                <input type="checkbox" id="notification-push-toggle" class="h-4 w-4 rounded border-slate-300 text-brand-600">
                            </label>
                            <p id="notification-push-hint" class="mt-1 text-[11px] text-slate-400">Get alerts even when this tab is closed.</p>
                        </div>

                        <a href="{{ route('notifications.index') }}"
                           class="block border-t border-slate-200 px-3 py-2 text-center text-xs font-semibold text-brand-600 hover:bg-slate-50 dark:border-slate-800 dark:text-brand-300 dark:hover:bg-slate-800">
                            View all
                        </a>
                    </div>

                    @php
                        $notificationConfig = [
                            'feedUrl'        => route('notifications.feed'),
                            'subscribeUrl'   => route('push.subscribe'),
                            'unsubscribeUrl' => route('push.unsubscribe'),
                            'swUrl'          => url('/sw.js'),
                            'vapidKey'       => app(\App\Services\WebPushService::class)->publicKey(),
                            'pollActive'     => 30000,
                            'pollIdle'       => 120000,
                        ];
                    @endphp
                    <script type="application/json" id="sd-notification-config">@json($notificationConfig)</script>
                </div>

                @permission('tickets.create')
                    <a href="{{ route('tickets.new') }}"
                       class="flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        <span class="hidden sm:inline">New ticket</span>
                    </a>
                @endpermission

                {{-- Account menu — sign out lives here as well as in the sidebar --}}
                <div class="relative" id="account-menu">
                    <button type="button" id="account-button" aria-haspopup="true" aria-expanded="false"
                            class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-1.5 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                        <x-user-avatar :user="$user" class="h-8 w-8" />
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>

                    <div id="account-dropdown"
                         class="absolute right-0 top-full z-40 mt-2 hidden w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-raised dark:border-slate-700 dark:bg-slate-800">
                        <div class="border-b border-slate-200 px-3.5 py-3 dark:border-slate-700">
                            <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                            <span class="mt-1.5 inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-200">
                                {{ $user->role?->name }}
                            </span>
                        </div>
                        <a href="{{ route('notifications.index') }}"
                           class="flex items-center gap-2 px-3.5 py-2.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 9a6 6 0 1 0-12 0c0 4-2 5-2 5h16s-2-1-2-5M10.5 19a2 2 0 0 0 3 0" />
                            </svg>
                            Notifications
                            @if(($unreadNotificationCount ?? 0) > 0)
                                <span class="ml-auto rounded-full bg-red-100 px-1.5 text-[11px] font-semibold text-red-700 dark:bg-red-500/20 dark:text-red-300">{{ $unreadNotificationCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('profile.edit') }}"
                           class="flex items-center gap-2 px-3.5 py-2.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8v-1a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v1" />
                            </svg>
                            My profile
                        </a>
                        <a href="{{ route('password.edit') }}"
                           class="flex items-center gap-2 px-3.5 py-2.5 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/60">
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M7 11V7a5 5 0 0 1 10 0v4M5 11h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z" />
                            </svg>
                            Change password
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-200 dark:border-slate-700">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2 px-3.5 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M15 17v1a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v1M19 12H9m10 0-3-3m3 3-3 3" />
                                </svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- ---------------------------------------------------------- content --}}
        <main class="flex-1 p-4 lg:p-6">
            @if(session('success'))
                <div class="mb-4 flex gap-2.5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd" /></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 flex gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 10a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" /></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any() && ! $errors->has('email'))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    <p class="font-medium">Please correct the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- A view may supply its own hero banner instead of the plain heading. --}}
            @hasSection('hero')
                @yield('hero')
            @else
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">@yield('title', 'Dashboard')</h1>
                        @hasSection('subtitle')
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">@yield('subtitle')</p>
                        @endif
                    </div>
                    @hasSection('actions')
                        <div class="flex flex-wrap items-center gap-2">@yield('actions')</div>
                    @endif
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        // Theme toggle — remembered per browser.
        var toggle = document.getElementById('theme-toggle');
        toggle.addEventListener('click', function () {
            var dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('sd-theme', dark ? 'dark' : 'light');
        });

        // Mobile navigation drawer.
        var drawer = document.getElementById('mobile-nav');
        document.getElementById('open-nav').addEventListener('click', function () {
            drawer.classList.remove('hidden');
        });
        drawer.querySelectorAll('[data-close-nav]').forEach(function (el) {
            el.addEventListener('click', function () { drawer.classList.add('hidden'); });
        });

        // Account dropdown.
        var button = document.getElementById('account-button');
        var dropdown = document.getElementById('account-dropdown');
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            var open = dropdown.classList.toggle('hidden');
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
        document.addEventListener('click', function (event) {
            if (! dropdown.classList.contains('hidden') && ! dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                dropdown.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
                drawer.classList.add('hidden');
            }
        });
    })();
</script>

</body>
</html>
