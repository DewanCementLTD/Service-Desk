@extends('layouts.app')

@section('title', 'My Dashboard')
@section('subtitle', 'Welcome back, ' . auth()->user()->name)

@section('content')

    {{-- ------------------------------------------------------------ tickets --}}
    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
        My tickets
    </h2>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat-card label="Total raised" :value="$ticketCounts['total']" href="{{ route('tickets.index') }}" />
        <x-stat-card label="Open"         :value="$ticketCounts['open_count'] + $ticketCounts['in_progress']" tone="primary"
                     href="{{ route('tickets.index') }}" />
        <x-stat-card label="Pending"      :value="$ticketCounts['pending']"  tone="warning" />
        <x-stat-card label="Closed"       :value="$ticketCounts['closed']"   tone="success" />
    </div>

    {{-- --------------------------------------------------- development --}}
    @if($devCounts['total'] > 0)
        <h2 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            My development requests
        </h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-stat-card label="Total raised"      :value="$devCounts['total']" href="{{ route('development.index') }}" />
            <x-stat-card label="Awaiting decision"  :value="$devCounts['awaiting_decision']" tone="warning" />
            <x-stat-card label="Completed"          :value="$devCounts['completed']" tone="success" />
            <x-stat-card label="Rejected"           :value="$devCounts['rejected']" tone="danger" />
        </div>
    @endif

    <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">

        <x-card title="My tickets" subtitle="Everything you have reported">
            <x-slot:action>
                @permission('tickets.create')
                    <a href="{{ route('tickets.new') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400">Raise a request</a>
                @endpermission
            </x-slot:action>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($myTickets as $ticket)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-brand-600 dark:text-brand-400">{{ $ticket->ticket_no }}</a>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $ticket->subject }}</p>
                                </td>
                                <td class="px-2 py-2.5"><x-badge :color="$ticket->status?->color">{{ $ticket->status?->name }}</x-badge></td>
                                <td class="px-4 py-2.5 text-right text-xs text-slate-500 whitespace-nowrap">{{ $ticket->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="3" message="You have not raised any tickets yet." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="My development requests" subtitle="New systems and changes you have asked for">
            <x-slot:action>
                @permission('development.create')
                    <a href="{{ route('tickets.new') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400">Request something new</a>
                @endpermission
            </x-slot:action>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($myDevelopmentRequests as $request)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('development.show', $request) }}" class="font-medium text-brand-600 dark:text-brand-400">{{ $request->request_no }}</a>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $request->title }}</p>
                                </td>
                                <td class="px-2 py-2.5"><x-badge :color="$request->status?->color">{{ $request->status?->name }}</x-badge></td>
                                <td class="px-4 py-2.5 text-right text-xs text-slate-500 whitespace-nowrap">{{ $request->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="3" message="You have not raised any development requests yet." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

@endsection
