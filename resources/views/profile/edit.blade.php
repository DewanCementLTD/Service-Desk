@extends('layouts.app')

@section('title', 'My profile')
@section('subtitle', 'Your photo, your contact number and how to change your password.')

@section('content')

<div class="grid max-w-5xl grid-cols-1 gap-4 lg:grid-cols-3">

    {{-- ------------------------------------------------------------- photo --}}
    <div class="space-y-4">
        <x-card title="Photo" subtitle="Shown next to your name and on your comments.">
            <div class="flex flex-col items-center gap-3 p-4">
                @if($profileUser->avatar_url)
                    <img src="{{ $profileUser->avatar_url }}" alt="{{ $profileUser->name }}"
                         class="h-28 w-28 rounded-full object-cover ring-2 ring-slate-200 dark:ring-slate-700">
                @else
                    <span class="flex h-28 w-28 items-center justify-center rounded-full bg-brand-100 text-3xl font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-200">
                        {{ $profileUser->initials }}
                    </span>
                @endif

                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="w-full space-y-2">
                    @csrf
                    <x-field name="avatar" help="JPG, PNG or WebP, up to 2 MB.">
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs dark:border-slate-700 dark:bg-slate-800">
                    </x-field>
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                        Upload photo
                    </button>
                </form>

                @if($profileUser->avatar_path)
                    <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="w-full">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            Remove photo
                        </button>
                    </form>
                @endif
            </div>
        </x-card>

        <x-card title="Password">
            <div class="space-y-3 p-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if($profileUser->password_changed_at)
                        Last changed {{ $profileUser->password_changed_at->diffForHumans() }}.
                    @else
                        You are still on the password IT issued you.
                    @endif
                </p>
                <a href="{{ route('password.edit') }}"
                   class="block rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    Change password
                </a>
            </div>
        </x-card>
    </div>

    {{-- ------------------------------------------------------- your details --}}
    <div class="space-y-4 lg:col-span-2">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <x-card title="Your details"
                    subtitle="Your name, role and access are maintained by IT — ask them if something here is wrong.">
                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                    <x-field label="Full name" name="name">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $profileUser->name }}</p>
                    </x-field>

                    <x-field label="Employee number" name="employee_no">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $profileUser->employee_no ?: '—' }}</p>
                    </x-field>

                    <x-field label="Email address" name="email">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $profileUser->email }}</p>
                    </x-field>

                    <x-field label="Mobile number" name="phone" help="You can sign in with this number as well as your email.">
                        <input id="phone" name="phone" value="{{ old('phone', $profileUser->phone) }}" maxlength="30"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Role" name="role">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $profileUser->role?->name ?? '—' }}</p>
                    </x-field>

                    <x-field label="Designation" name="designation">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">{{ $profileUser->designation?->name ?: '—' }}</p>
                    </x-field>

                    <x-field label="Department" name="department"
                             help="{{ $profileUser->department_id ? 'Tickets you raise are filed against this department.' : 'No department on file — ask IT to set yours, or your tickets will be filed without one.' }}">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                            {{ $profileUser->department?->name ?? 'Not set' }}
                        </p>
                    </x-field>

                    <x-field label="Head of department" name="hod"
                             help="Set on the department by a super admin (Manage Departments). Your HOD is copied on your ticket emails.">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                            @if ($profileUser->hod())
                                {{ $profileUser->hod()->name }}
                                <span class="text-slate-400">— {{ $profileUser->hod()->email }}</span>
                            @else
                                Not set
                            @endif
                        </p>
                    </x-field>

                    <x-field label="Company" name="company">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                            {{ $profileUser->department?->company?->name ?? $profileUser->defaultCompany?->name ?? '—' }}
                        </p>
                    </x-field>
                </div>

                <div class="flex items-center gap-2 border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                        Save changes
                    </button>
                </div>
            </x-card>
        </form>

        <x-card title="Your access" subtitle="The companies and locations you can work in.">
            <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Companies</p>
                    @if($profileUser->is_all_companies)
                        <p class="text-sm">All companies in the group</p>
                    @else
                        <ul class="space-y-1 text-sm">
                            @forelse($companies as $company)
                                <li class="truncate">{{ $company->name }}</li>
                            @empty
                                <li class="text-slate-500">None granted</li>
                            @endforelse
                        </ul>
                    @endif
                </div>

                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Locations</p>
                    @if($profileUser->is_all_locations)
                        <p class="text-sm">All locations</p>
                    @else
                        <ul class="space-y-1 text-sm">
                            @forelse($locations as $location)
                                <li class="truncate">{{ $location->name }}</li>
                            @empty
                                <li class="text-slate-500">None granted</li>
                            @endforelse
                        </ul>
                    @endif
                </div>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                Last signed in
                {{ $profileUser->last_login_at ? $profileUser->last_login_at->format('d M Y, H:i') : 'never' }}
                @if($profileUser->last_login_ip) from {{ $profileUser->last_login_ip }} @endif
            </div>
        </x-card>
    </div>
</div>

@endsection
