@extends('layouts.app')

@section('title', 'Organisations')

@section('actions')
    <a href="{{ route('admin.organizations.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">New organisation</a>
@endsection

@section('content')

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Code</th>
                    <th class="px-2 py-2.5 text-left font-medium">Name</th>
                    <th class="px-2 py-2.5 text-left font-medium">Country</th>
                    <th class="px-2 py-2.5 text-right font-medium">Companies</th>
                    <th class="px-2 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-right font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($organizations as $organization)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $organization->code }}</td>
                        <td class="px-2 py-2.5 font-medium">{{ $organization->name }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $organization->country ?? '—' }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $organization->companies_count }}</td>
                        <td class="px-2 py-2.5"><x-badge :color="$organization->is_active ? 'green' : 'slate'">{{ $organization->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('admin.organizations.edit', $organization) }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Edit</a>
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="6" message="No organisations defined." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($organizations->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $organizations->links() }}</div>
    @endif
</x-card>

@endsection
