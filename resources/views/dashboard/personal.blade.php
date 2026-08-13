@extends('layouts.app')

@section('title', 'My Dashboard')
@section('subtitle', 'Welcome back, ' . auth()->user()->name)

@section('content')

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat-card label="My open requests" :value="$counts['open_requests']" tone="info"    href="{{ route('tickets.index') }}" />
        <x-stat-card label="Assigned to me"   :value="$counts['assigned_open']" tone="primary" href="{{ route('tickets.index') }}" />
        <x-stat-card label="My tasks"         :value="$counts['my_tasks']"                     href="{{ route('tasks.index') }}" />
        <x-stat-card label="Overdue"          :value="$counts['overdue']"       tone="danger" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">

        <x-card title="My requests" subtitle="Tickets you raised">
            <x-slot:action>
                @permission('tickets.create')
                    <a href="{{ route('tickets.create') }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Raise a ticket</a>
                @endpermission
            </x-slot:action>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($myTickets as $ticket)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $ticket->ticket_no }}</a>
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

        <x-card title="Assigned to me" subtitle="Open tickets in your queue">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($assigned as $ticket)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $ticket->ticket_no }}</a>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-600 dark:text-slate-400">{{ $ticket->subject }}</p>
                                </td>
                                <td class="px-2 py-2.5"><x-badge :color="$ticket->priority?->color">{{ $ticket->priority?->name }}</x-badge></td>
                                <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap {{ $ticket->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                                    {{ $ticket->due_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="3" message="Nothing assigned to you." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-card title="My tasks" class="mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($myTasks as $task)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('tasks.show', $task) }}" class="font-medium text-indigo-600 dark:text-indigo-400">{{ $task->task_no }}</a>
                                <p class="mt-0.5 max-w-md truncate text-xs text-slate-600 dark:text-slate-400">{{ $task->title }}</p>
                            </td>
                            <td class="px-2 py-2.5"><x-badge :color="$task->status?->color">{{ $task->status?->name }}</x-badge></td>
                            <td class="px-2 py-2.5 text-right text-xs tabular-nums text-slate-500">{{ $task->progress_percent }}%</td>
                            <td class="px-4 py-2.5 text-right text-xs whitespace-nowrap {{ $task->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">
                                {{ $task->due_at?->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="4" message="No tasks assigned to you." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

@endsection
