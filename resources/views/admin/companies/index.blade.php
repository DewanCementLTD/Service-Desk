@extends('layouts.app')

@section('title', 'Companies')

@section('actions')
    <a href="{{ route('admin.companies.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New company</a>
@endsection

@section('content')

<form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
    <select name="organization_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
        <option value="">All organisations</option>
        @foreach($organizations as $organization)
            <option value="{{ $organization->id }}" @selected(request('organization_id') == $organization->id)>{{ $organization->name }}</option>
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
                    <th class="px-2 py-2.5 text-left font-medium">Organisation</th>
                    <th class="px-2 py-2.5 text-left font-medium">Ticket prefix</th>
                    <th class="px-2 py-2.5 text-right font-medium">Locations</th>
                    <th class="px-2 py-2.5 text-right font-medium">Departments</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($companies as $company)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $company->code }}</td>
                        <td class="px-2 py-2.5 font-medium">{{ $company->name }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $company->organization?->name }}</td>
                        <td class="px-2 py-2.5"><x-badge color="blue">{{ $company->ticket_prefix }}-YYYY-000001</x-badge></td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $company->locations_count }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $company->departments_count }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$company->is_active ? 'green' : 'slate'">{{ $company->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('admin.companies.edit', $company) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Edit</a>
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="8" message="No companies defined." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($companies->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $companies->links() }}</div>
    @endif
</x-card>

@endsection
