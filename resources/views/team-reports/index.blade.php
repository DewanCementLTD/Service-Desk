@extends('layouts.app')

@section('title', 'Team Reports Dashboard')

@section('content')
<div class="container mx-auto py-6 px-4">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Team Reports</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
            Monitor your team's progress and workload
        </p>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Period Filter -->
                <div>
                    <label for="period" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                        Time Period
                    </label>
                    <select name="period" id="period" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                        <option value="week" {{ $filters['period'] === 'week' ? 'selected' : '' }}>This Week</option>
                        <option value="month" {{ $filters['period'] === 'month' ? 'selected' : '' }}>This Month</option>
                        <option value="all" {{ $filters['period'] === 'all' ? 'selected' : '' }}>All Time</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                        Status
                    </label>
                    <select name="status" id="status" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        @foreach($availableStatuses as $code => $name)
                            <option value="{{ $code }}" {{ $filters['status'] === $code ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Member Filter -->
                <div>
                    <label for="member" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                        Team Member
                    </label>
                    <select name="member" id="member" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                        <option value="">All Members</option>
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" {{ $filters['member'] === $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    Apply Filters
                </button>
                <a href="{{ route('team-reports.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Team Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <!-- Total Pending Work -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Pending Work</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['total_work'] }}</p>
                </div>
                <div class="h-12 w-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Task Completion Rate -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Avg Task Completion</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['avg_task_rate'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $totals['total_completed_tasks'] }}/{{ $totals['total_tasks'] }} completed</p>
                </div>
                <div class="h-12 w-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Ticket Resolution Rate -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Avg Ticket Resolution</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['avg_ticket_rate'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $totals['total_resolved_tickets'] }}/{{ $totals['total_tickets'] }} resolved</p>
                </div>
                <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Average Reported Progress -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Avg Task Progress</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['avg_progress'] }}%</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Reported on tasks</p>
                </div>
                <div class="h-12 w-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Overdue Items -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Overdue Items</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $totals['total_overdue'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Requires attention</p>
                </div>
                <div class="h-12 w-12 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Members Table -->
    @if($metrics->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Team Member</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Pending Tasks</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Task Completion</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Task Progress</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Pending Tickets</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Ticket Resolution</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Overdue</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Total Work</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($metrics as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    @if($item['member']->avatar_path)
                                        <img class="h-10 w-10 rounded-full object-cover" src="{{ $item['member']->getAvatarUrlAttribute() }}" alt="{{ $item['member']->name }}">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-sm">
                                            {{ $item['member']->initials }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['member']->name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['member']->employee_no ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                {{ $item['total_tasks'] - $item['completed_tasks'] }} / {{ $item['total_tasks'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $item['task_completion_rate'] }}%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['task_completion_rate'] }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($item['total_tasks'] > 0)
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $item['avg_progress'] }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['avg_progress'] }}%</span>
                                </div>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
                                {{ $item['total_tickets'] - $item['resolved_tickets'] }} / {{ $item['total_tickets'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $item['ticket_resolution_rate'] }}%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['ticket_resolution_rate'] }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($item['overdue'] > 0)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                                    {{ $item['overdue'] }} item(s)
                                </span>
                            @else
                                <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                @if($item['pending_work'] > 10) bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400
                                @elseif($item['pending_work'] > 5) bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400
                                @else bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400
                                @endif">
                                {{ $item['pending_work'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                No team members
            </h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Start by adding team members to view their progress metrics.
            </p>
        </div>
    @endif

    <!-- Legend -->
    <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-2">About These Metrics</h3>
        <ul class="text-sm text-blue-800 dark:text-blue-400 space-y-1">
            <li>• <strong>Pending Tasks:</strong> Tasks assigned but not yet completed</li>
            <li>• <strong>Task Completion:</strong> Percentage of assigned tasks marked as completed</li>
            <li>• <strong>Task Progress:</strong> Average progress % reported on assigned tasks, cancelled ones excluded — how far along the work is, not just what has finished</li>
            <li>• <strong>Pending Tickets:</strong> Tickets assigned but not yet resolved</li>
            <li>• <strong>Ticket Resolution:</strong> Percentage of assigned tickets marked as resolved</li>
            <li>• <strong>Overdue:</strong> Items past their due date still pending</li>
            <li>• <strong>Total Work:</strong> Sum of pending tasks and tickets (red/orange = high workload)</li>
        </ul>
    </div>
</div>
@endsection
