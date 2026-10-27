@extends('layouts.app')

@section('title', 'Add Team Member')

@section('content')
<div class="container mx-auto py-6 px-4 max-w-2xl">
    <!-- Back Link -->
    <a href="{{ route('team-lead.index') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mb-6">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to Team
    </a>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Add Team Member</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
            @if($isAdmin)
                Choose a Team Lead and the member to add to their team. They'll be able to assign tasks and manage their work.
            @else
                Select a team member to add to your team. You'll be able to assign tasks and manage their work.
            @endif
        </p>
    </div>

    <!-- Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <form method="POST" action="{{ route('team-lead.store') }}" class="space-y-6">
            @csrf

            {{-- An admin builds somebody else's team, so the lead is a choice
                 rather than the signed-in user. --}}
            @if($isAdmin)
            <div>
                <label for="team_lead_id" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                    Select Team Lead <span class="text-red-500">*</span>
                </label>

                <select name="team_lead_id" id="team_lead_id"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('team_lead_id') border-red-500 @enderror"
                        required>
                    <option value="">Choose a team lead...</option>
                    @foreach($teamLeads as $lead)
                        <option value="{{ $lead->id }}" @selected(old('team_lead_id') == $lead->id)>
                            {{ $lead->name }} ({{ $lead->employee_no ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>

                @if($teamLeads->count() === 0)
                    <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
                        No active users hold the Team Lead role yet.
                    </p>
                @endif

                @error('team_lead_id')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            @endif

            <!-- Team Member Selection -->
            <div>
                <label for="team_member_id" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                    Select Team Member <span class="text-red-500">*</span>
                </label>

                <div class="relative">
                    <select name="team_member_id" id="team_member_id"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('team_member_id') border-red-500 @enderror"
                            required>
                        <option value="">Choose a team member...</option>
                        @foreach($availableUsers as $user)
                            <option value="{{ $user->id }}" data-department="{{ $user->department?->name }}" @selected(old('team_member_id') == $user->id)>
                                {{ $user->name }} ({{ $user->employee_no ?? 'N/A' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($availableUsers->count() === 0)
                    <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400 flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $isAdmin ? 'No active IT support staff are available to assign.' : 'All available users are already on your team.' }}</span>
                    </p>
                @endif

                @error('team_member_id')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18.101 12.93a1 1 0 00-1.414-1.414L10 16.586l-6.687-6.687a1 1 0 00-1.414 1.414l8.1 8.1a1 1 0 001.414 0l9.1-9.1z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <!-- User Info Display (JavaScript) -->
            <div id="userInfo" class="hidden bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Email</p>
                        <p id="userEmail" class="text-sm font-medium text-gray-900 dark:text-white mt-1">—</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Department</p>
                        <p id="userDept" class="text-sm font-medium text-gray-900 dark:text-white mt-1">—</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Employee Number</p>
                        <p id="userEmpNo" class="text-sm font-medium text-gray-900 dark:text-white mt-1">—</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 uppercase tracking-wide">Status</p>
                        <p id="userStatus" class="text-sm font-medium text-gray-900 dark:text-white mt-1">
                            <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded text-xs">Active</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h3 class="text-sm font-medium text-blue-900 dark:text-blue-300 flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0zM8 9a1 1 0 000-2 1 1 0 000 2zm1 4a1 1 0 11-2 0 1 1 0 012 0zm4-4a1 1 0 11-2 0 1 1 0 012 0zm1 4a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                    </svg>
                    <span>
                        @if($isAdmin)
                            Once added, this member appears under their Team Lead, who can then assign tasks to them.
                        @else
                            Once added, this team member will appear in your team list and you can assign tasks to them.
                        @endif
                    </span>
                </h3>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 pt-4">
                <button type="submit"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Team Member
                </button>
                <a href="{{ route('team-lead.index') }}"
                   class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-medium transition text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Help Section -->
    <div class="mt-8 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Need Help?</h3>
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li class="flex items-start">
                <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold mr-3 flex-shrink-0">1</span>
                <span>Select an employee from the dropdown list</span>
            </li>
            <li class="flex items-start">
                <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold mr-3 flex-shrink-0">2</span>
                <span>Review their information in the preview box</span>
            </li>
            <li class="flex items-start">
                <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold mr-3 flex-shrink-0">3</span>
                <span>Click "Add Team Member" to confirm the assignment</span>
            </li>
            <li class="flex items-start">
                <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-bold mr-3 flex-shrink-0">4</span>
                <span>You can now assign tasks to this team member from your team page</span>
            </li>
        </ul>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('team_member_id');
        const userInfo = document.getElementById('userInfo');
        const userEmail = document.getElementById('userEmail');
        const userDept = document.getElementById('userDept');
        const userEmpNo = document.getElementById('userEmpNo');

        // Store user data as data attributes
        const userDataMap = {
            @foreach($availableUsers as $user)
            '{{ $user->id }}': {
                name: '{{ $user->name }}',
                email: '{{ $user->email }}',
                empNo: '{{ $user->employee_no ?? "N/A" }}',
                dept: '{{ $user->department?->name ?? "—" }}'
            }{{ !$loop->last ? ',' : '' }}
            @endforeach
        };

        select.addEventListener('change', function() {
            if (this.value && userDataMap[this.value]) {
                const userData = userDataMap[this.value];
                userEmail.textContent = userData.email;
                userDept.textContent = userData.dept;
                userEmpNo.textContent = userData.empNo;
                userInfo.classList.remove('hidden');
            } else {
                userInfo.classList.add('hidden');
            }
        });
    });
</script>
@endsection
