@extends('layouts.app')

@section('title', 'Development Report')
@section('subtitle', $from->format('d M Y') . ' – ' . $to->format('d M Y'))

@section('content')

@include('reports._range')

<x-card title="Pipeline by status">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Status</th>
                    <th class="px-2 py-2 text-right font-medium">Total</th>
                    <th class="px-2 py-2 text-right font-medium">Approved</th>
                    <th class="px-2 py-2 text-right font-medium">Pending</th>
                    <th class="px-2 py-2 text-right font-medium">Avg progress</th>
                    <th class="px-2 py-2 text-right font-medium">Estimated (h)</th>
                    <th class="px-4 py-2 text-right font-medium">Actual (h)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $row)
                    @php $r = array_change_key_case((array) $row, CASE_LOWER); @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $r['status_name'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ $r['total'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-green-600">{{ $r['approved'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-amber-600">{{ $r['pending'] }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ $r['avg_progress'] ?? 0 }}%</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ number_format((float) $r['estimated_hours'], 1) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $r['actual_hours'], 1) }}</td>
                    </tr>
                @empty
                    <x-empty-state colspan="7" message="No development requests in this period." />
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

@endsection
