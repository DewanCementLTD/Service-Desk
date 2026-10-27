@extends('layouts.app')

@section('title', 'Roles & permissions')
@section('subtitle', 'A user inherits every permission ticked on their role.')

@section('actions')
    <a href="{{ route('admin.roles.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New role</a>
@endsection

@section('content')

<form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search code or name"
           class="w-64 rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Search</button>
</form>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Code</th>
                    <th class="px-2 py-2.5 text-left font-medium">Name</th>
                    <th class="px-2 py-2.5 text-right font-medium">Level</th>
                    <th class="px-2 py-2.5 text-right font-medium">Permissions</th>
                    <th class="px-2 py-2.5 text-right font-medium">Users</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($roles as $role)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5 font-mono text-xs">
                            {{ $role->code }}
                            @if($role->is_system)<x-badge color="slate" class="ml-1">built-in</x-badge>@endif
                        </td>
                        <td class="px-2 py-2.5">
                            <span class="font-medium">{{ $role->name }}</span>
                            <p class="mt-0.5 max-w-md truncate text-xs text-slate-500">{{ $role->description }}</p>
                        </td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-slate-500">{{ $role->hierarchy_level }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">
                            @if($role->code === \App\Models\Role::ADMIN)
                                <x-badge color="purple">all</x-badge>
                            @else
                                {{ $role->permissions_count }}
                            @endif
                        </td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $role->users_count }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$role->is_active ? 'green' : 'slate'">{{ $role->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                            @unless($role->is_system && $role->is_active)
                                <form method="POST" action="{{ route('admin.roles.toggle', $role) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:underline">
                                        {{ $role->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @endunless
                            @unless($role->is_system)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Delete role &quot;{{ $role->name }}&quot;? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 hover:underline" @if($role->users_count > 0) disabled title="Cannot delete a role with {{ $role->users_count }} assigned user(s)" @endif>
                                        @if($role->users_count > 0)
                                            Delete ({{ $role->users_count }} users)
                                        @else
                                            Delete
                                        @endif
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="7" message="No roles defined." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($roles->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $roles->links() }}</div>
    @endif
</x-card>

@endsection
