@extends('layouts.app')

@section('title', 'Locations')

@section('actions')
    <a href="{{ route('admin.locations.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New location</a>
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
                    <th class="px-2 py-2.5 text-left font-medium">Type</th>
                    <th class="px-2 py-2.5 text-left font-medium">City</th>
                    <th class="px-2 py-2.5 text-left font-medium">Manager</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($locations as $location)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $location->code }}</td>
                        <td class="px-2 py-2.5 font-medium">
                            {{ $location->name }}
                            @if($location->is_head_office)<x-badge color="indigo" class="ml-1">HO</x-badge>@endif
                        </td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $location->company?->name }}</td>
                        <td class="px-2 py-2.5 text-xs">{{ $location->type_label }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $location->city ?? '—' }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $location->manager?->name ?? '—' }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$location->is_active ? 'green' : 'slate'">{{ $location->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('admin.locations.edit', $location) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Edit</a>
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="8" message="No locations defined." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($locations->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $locations->links() }}</div>
    @endif
</x-card>

@endsection
