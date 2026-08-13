@extends('layouts.app')

@section('title', 'Departments')

@section('actions')
    <a href="{{ route('admin.departments.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New department</a>
@endsection

@section('content')

<form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
    <select name="company_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All companies</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Filter</button>
</form>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Code</th>
                    <th class="px-2 py-2.5 text-left font-medium">Name</th>
                    <th class="px-2 py-2.5 text-left font-medium">Company</th>
                    <th class="px-2 py-2.5 text-left font-medium">Location</th>
                    <th class="px-2 py-2.5 text-left font-medium">Head</th>
                    <th class="px-2 py-2.5 text-right font-medium">Users</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($departments as $department)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $department->code }}</td>
                        <td class="px-2 py-2.5 font-medium">
                            {{ $department->name }}
                            @if($department->is_it_team)<x-badge color="blue" class="ml-1">IT team</x-badge>@endif
                        </td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $department->company?->name }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $department->location?->name ?? '—' }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $department->head?->name ?? '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $department->users_count }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$department->is_active ? 'green' : 'slate'">{{ $department->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('admin.departments.edit', $department) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Edit</a>
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="8" message="No departments defined." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($departments->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $departments->links() }}</div>
    @endif
</x-card>

@endsection
