@extends('layouts.app')

@section('title', 'Ticket Summary Report')
@section('subtitle', $from->format('d M Y') . ' – ' . $to->format('d M Y') . ' · ' . ($activeCompany?->name ?? 'all companies'))

@section('actions')
    @permission('reports.export')
        <a href="{{ route('reports.tickets.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Export CSV</a>
    @endpermission
@endsection

@section('content')

@include('reports._range')

<x-card title="By month">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Period</th>
                    <th class="px-2 py-2 text-right font-medium">Total</th>
                    <th class="px-2 py-2 text-right font-medium">Closed</th>
                    <th class="px-2 py-2 text-right font-medium">Active</th>
                    <th class="px-2 py-2 text-right font-medium">SLA breached</th>
                    <th class="px-2 py-2 text-right font-medium">Critical</th>
                    <th class="px-4 py-2 text-right font-medium">Avg resolution (h)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $row)
                    @php $r = array_change_key_case((array) $row, CASE_LOWER); @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $r['period'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ $r['total'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-green-600">{{ $r['closed'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ $r['active'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-red-600">{{ $r['sla_breached'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ $r['critical'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $r['avg_resolution_hours'] ?? '—' }}</td>
                    </tr>
                @empty
                    <x-empty-state colspan="7" message="No tickets in this period." />
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <x-card title="By department">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Department</th>
                        <th class="px-2 py-2 text-right font-medium">Total</th>
                        <th class="px-2 py-2 text-right font-medium">Active</th>
                        <th class="px-4 py-2 text-right font-medium">Overdue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($departments as $dept)
                        @php $r = array_change_key_case((array) $dept, CASE_LOWER); @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $r['department_name'] }}</td>
                            <td class="px-2 py-2 text-right tabular-nums">{{ $r['total_tickets'] }}</td>
                            <td class="px-2 py-2 text-right tabular-nums">{{ $r['active_tickets'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-red-600">{{ $r['overdue_tickets'] }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="4" message="No data." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="By agent">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Agent</th>
                        <th class="px-2 py-2 text-right font-medium">Assigned</th>
                        <th class="px-2 py-2 text-right font-medium">Closed</th>
                        <th class="px-4 py-2 text-right font-medium">Avg hrs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($team as $agent)
                        @php $r = array_change_key_case((array) $agent, CASE_LOWER); @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $r['agent_name'] }}</td>
                            <td class="px-2 py-2 text-right tabular-nums">{{ $r['assigned_tickets'] }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-green-600">{{ $r['completed_tickets'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $r['avg_resolution_hours'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="4" message="No data." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>

@endsection
