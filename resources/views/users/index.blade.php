@extends('layouts.app')

@section('title', 'Users')
@section('subtitle', $users->total() . ' user account(s)')

@section('actions')
    @permission('users.manage')
        <a href="{{ route('users.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New user</a>
    @endpermission
@endsection

@section('content')

{{-- Shown once, straight after a reset. It is the only moment the password is
     readable anywhere - it is not stored and not written to the audit trail. --}}
@if($issued = session('temporaryPassword'))
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
            Temporary password issued to {{ $issued['name'] }}
        </p>
        <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
            Pass it on now — it is not shown again, and nobody can look it up later.
            {{ $issued['name'] }} must choose their own password at the next sign-in.
        </p>

        <div class="mt-3 flex flex-wrap gap-6">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Signs in with</p>
                <p class="font-mono text-lg font-bold text-amber-900 dark:text-amber-100">{{ $issued['login'] }}</p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Temporary password</p>
                <p class="font-mono text-lg font-bold tracking-widest text-amber-900 dark:text-amber-100">{{ $issued['password'] }}</p>
            </div>
        </div>
    </div>
@endif

<form method="GET" class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or employee no"
           class="col-span-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">

    <select name="role_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All roles</option>
        @foreach($roles as $role)
            <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? null) == $role->id)>{{ $role->name }}</option>
        @endforeach
    </select>

    <select name="company_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All companies</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
        @endforeach
    </select>

    <select name="location_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All locations</option>
        @foreach($locations as $location)
            <option value="{{ $location->id }}" @selected(($filters['location_id'] ?? null) == $location->id)>
                {{ $location->company?->code }} · {{ $location->name }}
            </option>
        @endforeach
    </select>

    <div class="flex gap-2">
        <button type="submit" class="flex-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Filter</button>
        <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">Reset</a>
    </div>
</form>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">User</th>
                    <th class="px-2 py-2.5 text-left font-medium">Role</th>
                    <th class="px-2 py-2.5 text-left font-medium">Department</th>
                    <th class="px-2 py-2.5 text-left font-medium">Default company</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($users as $user)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5">
                            <p class="font-medium">{{ $user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $user->email }} @if($user->employee_no) &middot; {{ $user->employee_no }} @endif</p>
                        </td>
                        <td class="px-2 py-2.5 text-xs">{{ $user->role?->name }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $user->department?->name ?? '—' }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">
                            {{ $user->is_all_companies ? 'All companies' : ($user->defaultCompany?->name ?? '—') }}
                        </td>
                        <td class="px-2 py-2.5">
                            <x-badge :color="$user->is_active ? 'green' : 'slate'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @permission('users.manage')
                                <div class="flex items-center justify-end gap-3">
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.resetPassword', $user) }}"
                                              onsubmit="return confirm('Issue a new temporary password for {{ $user->name }}? Their current password stops working immediately.')">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400">
                                                Reset password
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('users.edit', $user) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Edit</a>
                                </div>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="6" message="No users match these filters." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $users->links() }}</div>
    @endif
</x-card>

@endsection
