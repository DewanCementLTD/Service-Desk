@extends('layouts.app')

@section('title', 'Reports')
@section('subtitle', 'All figures are aggregated by Oracle for the company and location currently in scope.')

@section('content')

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Ticket summary', 'Volume, closure rate and resolution time by month.', 'reports.tickets'],
        ['SLA breaches', 'Every ticket past its resolution deadline.', 'reports.sla'],
        ['Development', 'Delivery pipeline and effort against estimate.', 'reports.development'],
    ] as [$title, $description, $route])
        <a href="{{ route($route) }}"
           class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
        </a>
    @endforeach
</div>

@permission('reports.export')
    <x-card title="Export" class="mt-4">
        <form method="GET" action="{{ route('reports.tickets.export') }}" class="flex flex-wrap items-end gap-3 p-4">
            <x-field label="From" name="from">
                <input type="date" name="from" value="{{ now()->subMonths(6)->format('Y-m-d') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>
            <x-field label="To" name="to">
                <input type="date" name="to" value="{{ now()->format('Y-m-d') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Download ticket register (CSV)
            </button>
        </form>
    </x-card>
@endpermission

@endsection
