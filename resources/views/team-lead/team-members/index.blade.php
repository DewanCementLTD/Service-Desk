@extends('layouts.app')

@section('title', 'Team Members')

@section('content')
<div class="container mx-auto py-6 px-4">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Team Members</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                @if($isAdmin)
                    View and manage all team assignments
                @else
                    Manage your team members and task assignments
                @endif
            </p>
        </div>
        <a href="{{ route('team-lead.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Team Member
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-start">
        <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
        </svg>
        <div>
            <h3 class="font-medium">Success</h3>
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    <!-- Team Members Table -->
    @if($teamMembers->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Team Member</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Employee No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Department</th>
                        @if($isAdmin)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Team Lead</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Assigned Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Assigned By</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($teamMembers as $assignment)
                    @php
                        $member = $assignment->teamMember;
                        $lead = $assignment->teamLead;
                        $assigner = $assignment->assignedBy;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    @if($member->avatar_path)
                                        <img class="h-10 w-10 rounded-full object-cover" src="{{ $member->getAvatarUrlAttribute() }}" alt="{{ $member->name }}">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-sm">
                                            {{ $member->initials }}
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $member->name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $member->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            {{ $member->employee_no ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            @if($member->department)
                                <span class="inline-block px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs">
                                    {{ $member->department->name }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        @if($isAdmin)
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8">
                                    @if($lead->avatar_path)
                                        <img class="h-8 w-8 rounded-full object-cover" src="{{ $lead->getAvatarUrlAttribute() }}" alt="{{ $lead->name }}">
                                    @else
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold text-xs">
                                            {{ $lead->initials }}
                                        </div>
                                    @endif
                                </div>
                                <span class="ml-2">{{ $lead->name }}</span>
                            </div>
                        </td>
                        @endif
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            @if($assignment->assigned_at)
                                <div title="{{ $assignment->assigned_at->format('M d, Y H:i') }}">
                                    {{ $assignment->assigned_at->format('M d, Y') }}
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            @if($assigner)
                                {{ $assigner->name }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            {{-- Admins manage every team, so they may unassign from any of them;
                                 a team lead only sees their own rows here to begin with.
                                 TeamLeadController::destroy() re-checks both. --}}
                            <form method="POST" action="{{ route('team-lead.destroy', $assignment) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        onclick="return confirm('Remove {{ $member->name }} from the team?')"
                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 font-medium transition">
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $teamMembers->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM6 20a9 9 0 0118 0v2h2v-2a11 11 0 00-22 0v2h2v-2z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                @if($isAdmin)
                    No team assignments yet
                @else
                    No team members assigned yet
                @endif
            </h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                @if($isAdmin)
                    Start by assigning a member to one of the team leads.
                @else
                    Start by adding your first team member.
                @endif
            </p>
            <div class="mt-6">
                <a href="{{ route('team-lead.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Team Member
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
