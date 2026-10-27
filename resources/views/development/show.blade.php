@extends('layouts.app')

@section('title', $request->request_no)
@section('subtitle', $request->title)

@section('actions')
    <a href="{{ route('development.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Back to list</a>
    @permission('development.delete')
        <form method="POST" action="{{ route('development.destroy', $request) }}"
              onsubmit="return confirm('Delete {{ $request->request_no }}? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500/40">Delete request</button>
        </form>
    @endpermission
@endsection

@section('content')

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

    <div class="space-y-4 xl:col-span-2">

        <x-card title="Business purpose">
            <div class="px-4 py-3 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $request->business_purpose ?: '—' }}</div>
        </x-card>

        <x-card title="Requirements">
            <div class="px-4 py-3 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $request->requirements ?: '—' }}</div>
        </x-card>

        @if($request->technical_notes)
            <x-card title="Technical notes">
                <div class="px-4 py-3 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $request->technical_notes }}</div>
            </x-card>
        @endif

        @permission('development.update')
            <x-card title="Update request">
                <form method="POST" action="{{ route('development.update', $request) }}" class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <x-field label="Title" name="title" :required="true" class="md:col-span-2">
                        <input name="title" value="{{ old('title', $request->title) }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Technical notes" name="technical_notes" class="md:col-span-2">
                        <textarea name="technical_notes" rows="4"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('technical_notes', $request->technical_notes) }}</textarea>
                    </x-field>

                    <x-field label="Status" name="development_status_id" :required="true">
                        <select name="development_status_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}" @selected($request->development_status_id == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Assigned developer" name="assigned_to">
                        <select name="assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                            <option value="">Unassigned</option>
                            @foreach($developers as $developer)
                                <option value="{{ $developer->id }}" @selected($request->assigned_to == $developer->id)>{{ $developer->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Due date" name="due_at">
                        <input type="date" name="due_at" value="{{ old('due_at', $request->due_at?->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Progress %" name="progress_percent">
                        <input type="number" name="progress_percent" min="0" max="100" value="{{ old('progress_percent', $request->progress_percent) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Estimated hours" name="estimated_hours">
                        <input type="number" step="0.5" min="0" name="estimated_hours" value="{{ old('estimated_hours', $request->estimated_hours) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <x-field label="Actual hours" name="actual_hours">
                        <input type="number" step="0.5" min="0" name="actual_hours" value="{{ old('actual_hours', $request->actual_hours) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </x-field>

                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
                    </div>
                </form>
            </x-card>
        @endpermission

        <x-card title="Progress updates">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($request->updates as $update)
                    <div class="px-4 py-3">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="text-sm font-medium">{{ $update->user?->name }}</span>
                            @if($update->progress_percent !== null)
                                <x-badge color="blue">{{ $update->progress_percent }}%</x-badge>
                            @endif
                            <span class="ml-auto text-xs text-slate-400">{{ $update->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $update->notes }}</p>
                    </div>
                @empty
                    <x-empty-state message="No progress updates yet." />
                @endforelse
            </div>

            <form method="POST" action="{{ route('development.progress', $request) }}" class="border-t border-slate-200 p-4 dark:border-slate-800">
                @csrf
                <textarea name="notes" rows="3" required placeholder="Post a progress update…"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <input type="number" name="progress_percent" min="0" max="100" placeholder="Progress %"
                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <input type="number" name="hours_spent" step="0.5" min="0" placeholder="Hours"
                           class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <button type="submit" class="ml-auto rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">Post update</button>
                </div>
            </form>
        </x-card>
    </div>

    <div class="space-y-4">
        <x-card title="Details">
            <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @foreach([
                    'Status'    => $request->status?->name,
                    'Approval'  => $request->approval_status,
                    'Requester' => $request->requester?->name,
                    'Developer' => $request->assignee?->name ?? 'Unassigned',
                    'Department'=> $request->department?->name,
                    'Priority'  => $request->priority?->name,
                    'Target'    => $request->target_date?->format('d M Y'),
                    'Due'       => $request->due_at?->format('d M Y'),
                    'Progress'  => $request->progress_percent . '%',
                    'Estimated' => $request->estimated_hours ? $request->estimated_hours . ' h' : null,
                    'Actual'    => $request->actual_hours ? $request->actual_hours . ' h' : null,
                    'Approved by' => $request->approver?->name,
                ] as $label => $value)
                    <div class="flex items-center justify-between gap-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="text-right text-slate-700 dark:text-slate-300">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        @permission('development.approve')
            <x-card title="Approval decision">
                <form method="POST" action="{{ route('development.decide', $request) }}" class="space-y-2 p-4">
                    @csrf
                    <select name="decision" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        <option value="APPROVED">Approve</option>
                        <option value="REJECTED">Reject</option>
                        <option value="ON_HOLD">Put on hold</option>
                    </select>
                    <textarea name="reason" rows="3" placeholder="Reason (required when rejecting)"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white dark:bg-slate-700">Record decision</button>
                </form>
            </x-card>
        @endpermission

        @if($request->rejection_reason)
            <x-card title="Rejection reason">
                <p class="px-4 py-3 text-sm text-red-600 dark:text-red-400">{{ $request->rejection_reason }}</p>
            </x-card>
        @endif
    </div>
</div>

@endsection
