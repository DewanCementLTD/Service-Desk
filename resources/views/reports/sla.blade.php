@extends('layouts.app')

@section('title', 'SLA Breach Report')
@section('subtitle', $breaches->total() . ' ticket(s) breaching or at risk')

@section('content')

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Ticket</th>
                    <th class="px-2 py-2 text-left font-medium">Company / location</th>
                    <th class="px-2 py-2 text-left font-medium">Priority</th>
                    <th class="px-2 py-2 text-left font-medium">Status</th>
                    <th class="px-2 py-2 text-left font-medium">Assignee</th>
                    <th class="px-2 py-2 text-left font-medium">SLA</th>
                    <th class="px-4 py-2 text-right font-medium">Hours overdue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($breaches as $row)
                    @php $r = array_change_key_case((array) $row, CASE_LOWER); @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('tickets.show', $r['ticket_id']) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $r['ticket_no'] }}</a>
                            <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $r['subject'] }}</p>
                        </td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">
                            {{ $r['company_name'] }}
                            @if($r['location_name'])<br><span class="text-slate-400">{{ $r['location_name'] }}</span>@endif
                        </td>
                        <td class="px-2 py-2.5 text-xs">{{ $r['priority_name'] }}</td>
                        <td class="px-2 py-2.5 text-xs">{{ $r['status_name'] }}</td>
                        <td class="px-2 py-2.5 text-xs text-slate-600 dark:text-slate-400">{{ $r['assignee_name'] ?? 'Unassigned' }}</td>
                        <td class="px-2 py-2.5">
                            <x-badge :color="$r['sla_status'] === 'BREACHED' ? 'red' : 'amber'">{{ str_replace('_', ' ', $r['sla_status']) }}</x-badge>
                        </td>
                        <td class="px-4 py-2.5 text-right text-xs font-medium tabular-nums text-red-600">
                            {{ $r['hours_overdue'] !== null ? number_format((float) $r['hours_overdue'], 1) : '—' }}
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="7" message="No SLA breaches in this scope." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if($breaches->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $breaches->links() }}</div>
    @endif
</x-card>

@endsection
