@extends('layouts.app')

@section('title', $ticket->ticket_no)
@section('subtitle', $ticket->subject)

@section('actions')
    @permission('tickets.update')
        <a href="{{ route('tickets.edit', $ticket) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Edit</a>
    @endpermission
    <a href="{{ route('tickets.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Back to list</a>
@endsection

@section('content')

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

    {{-- ------------------------------------------------------ main column --}}
    <div class="space-y-4 xl:col-span-2">

        <x-card title="Description">
            <div class="px-4 py-3 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">
                {{ $ticket->description ?: 'No description provided.' }}
            </div>

            @if($ticket->attachments->where('comment_id', null)->isNotEmpty())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Attachments</p>
                    <ul class="space-y-1">
                        @foreach($ticket->attachments->where('comment_id', null) as $file)
                            <li>
                                <a href="{{ route('tickets.attachment', $file) }}" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ $file->file_name }}
                                </a>
                                <span class="text-xs text-slate-400">({{ $file->human_size }})</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>

        @if($ticket->resolution)
            <x-card title="Resolution">
                <div class="px-4 py-3 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $ticket->resolution }}</div>
            </x-card>
        @endif

        {{-- ------------------------------------------------- activity stream --}}
        {{--
            Comments and history in one column, newest first. They were two
            separate cards, which meant reading a ticket's story required
            interleaving them by eye. The chips filter client-side — the whole
            stream is already on the page.
        --}}
        @php
            $stream = $ticket->comments
                ->reject(fn ($comment) => $comment->is_internal && ! $canSeeInternal)
                ->map(fn ($comment) => ['type' => 'comment', 'at' => $comment->created_at, 'model' => $comment])
                ->concat(
                    $ticket->history->map(fn ($entry) => ['type' => 'history', 'at' => $entry->created_at, 'model' => $entry])
                )
                ->sortByDesc('at')
                ->values();
        @endphp

        <x-card title="Activity">
            <x-slot:action>
                <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 text-xs dark:bg-slate-800" id="stream-filter">
                    @foreach(['all' => 'All', 'comment' => 'Comments', 'history' => 'History'] as $value => $label)
                        <button type="button" data-filter="{{ $value }}"
                                class="rounded-md px-2.5 py-1 font-medium transition {{ $value === 'all' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500 dark:text-slate-400' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </x-slot:action>

            <div class="divide-y divide-slate-100 dark:divide-slate-800" id="stream">
                @forelse($stream as $event)
                    @php $model = $event['model']; @endphp

                    @if($event['type'] === 'comment')
                        <div data-stream-item="comment"
                             class="px-4 py-3 {{ $model->is_internal ? 'bg-amber-50/60 dark:bg-amber-500/5' : '' }}">
                            <div class="mb-1 flex items-center gap-2">
                                <x-user-avatar :user="$model->user" class="h-6 w-6" text="text-[10px]" />
                                <span class="text-sm font-medium">{{ $model->user?->name }}</span>
                                @if($model->is_internal)
                                    <x-badge color="amber">Internal note</x-badge>
                                @endif
                                <span class="ml-auto text-xs text-slate-400" title="{{ $model->created_at?->format('d M Y H:i') }}">
                                    {{ $model->created_at?->diffForHumans() }}
                                </span>
                            </div>
                            <p class="text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-300">{{ $model->body }}</p>

                            @if($model->attachments->isNotEmpty())
                                <ul class="mt-2 space-y-0.5">
                                    @foreach($model->attachments as $file)
                                        <li>
                                            <a href="{{ route('tickets.attachment', $file) }}" class="text-xs text-brand-600 hover:underline dark:text-brand-300">{{ $file->file_name }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @else
                        <div data-stream-item="history" class="flex items-start gap-3 px-4 py-2.5 text-sm">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-slate-700 dark:text-slate-300">
                                    <span class="font-medium">{{ $model->user?->name ?? 'System' }}</span>
                                    {{ strtolower(str_replace('_', ' ', $model->action)) }}
                                    @if($model->old_value || $model->new_value)
                                        <span class="text-slate-500">— {{ $model->old_value ?: 'none' }} &rarr; {{ $model->new_value ?: 'none' }}</span>
                                    @endif
                                </p>
                                @if($model->notes)
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $model->notes }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 text-xs text-slate-400" title="{{ $model->created_at?->format('d M Y H:i') }}">
                                {{ $model->created_at?->diffForHumans() }}
                            </span>
                        </div>
                    @endif
                @empty
                    <x-empty-state message="Nothing has happened on this ticket yet." />
                @endforelse
            </div>

            <form method="POST" action="{{ route('tickets.comment', $ticket) }}" enctype="multipart/form-data"
                  class="border-t border-slate-200 p-4 dark:border-slate-800">
                @csrf
                <textarea name="body" rows="3" required placeholder="Add a comment… type @ and a colleague's name to notify them"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <input type="file" name="attachments[]" multiple
                           class="text-xs file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
                    @permission('tickets.comment_internal')
                        <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300">
                            Internal note (hidden from the requester)
                        </label>
                    @endpermission
                    <button type="submit" class="ml-auto rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-700">Post</button>
                </div>
            </form>
        </x-card>

        <script>
            (function () {
                var chips  = document.getElementById('stream-filter');
                var stream = document.getElementById('stream');

                if (! chips || ! stream) {
                    return;
                }

                chips.addEventListener('click', function (event) {
                    var button = event.target.closest('[data-filter]');

                    if (! button) {
                        return;
                    }

                    var wanted = button.dataset.filter;

                    chips.querySelectorAll('[data-filter]').forEach(function (chip) {
                        var on = chip === button;
                        chip.classList.toggle('bg-white', on);
                        chip.classList.toggle('dark:bg-slate-700', on);
                        chip.classList.toggle('shadow-sm', on);
                        chip.classList.toggle('text-slate-500', ! on);
                        chip.classList.toggle('dark:text-slate-400', ! on);
                    });

                    stream.querySelectorAll('[data-stream-item]').forEach(function (item) {
                        item.hidden = wanted !== 'all' && item.dataset.streamItem !== wanted;
                    });
                });
            })();
        </script>
    </div>

    {{-- ------------------------------------------------------- side column --}}
    <div class="space-y-4">

        <x-card title="Details">
            <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @foreach([
                    'Status'      => null,
                    'Priority'    => null,
                    'SLA'         => null,
                    'Category'    => $ticket->category?->name,
                    'Company'     => $ticket->company?->name,
                    'Location'    => $ticket->location?->name,
                    'Department'  => $ticket->department?->name,
                    'Requester'   => $ticket->requester?->name,
                    'Assignee'    => $ticket->assignee?->name ?? 'Unassigned',
                    'Created'     => $ticket->created_at?->format('d M Y H:i'),
                    'Due'         => $ticket->due_at?->format('d M Y H:i'),
                    'Resolved'    => $ticket->resolved_at?->format('d M Y H:i'),
                ] as $label => $value)
                    <div class="flex items-center justify-between gap-3 px-4 py-2">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="text-right">
                            @if($label === 'Status')
                                <x-badge :color="$ticket->status?->color">{{ $ticket->status?->name }}</x-badge>
                            @elseif($label === 'Priority')
                                <x-badge :color="$ticket->priority?->color">{{ $ticket->priority?->name }}</x-badge>
                            @elseif($label === 'SLA')
                                <x-badge :color="match($ticket->sla_status) { 'BREACHED' => 'red', 'AT_RISK' => 'amber', 'MET' => 'green', 'PAUSED' => 'slate', default => 'blue' }">
                                    {{ str_replace('_', ' ', $ticket->sla_status) }}
                                </x-badge>
                            @else
                                <span class="text-slate-700 dark:text-slate-300">{{ $value ?: '—' }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        @permission('tickets.assign')
            <x-card title="Assign">
                <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="space-y-2 p-4">
                    @csrf
                    <select name="assigned_to" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected($ticket->assigned_to == $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    <input name="note" placeholder="Note (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white dark:bg-slate-700">Assign</button>
                </form>
            </x-card>
        @endpermission

        @permission('tickets.update', 'tickets.close')
            <x-card title="Change status">
                <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="space-y-2 p-4">
                    @csrf
                    <select name="status_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}" @selected($ticket->status_id == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                    <input name="note" placeholder="Note (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white dark:bg-slate-700">Update status</button>
                </form>
            </x-card>
        @endpermission

        @permission('tickets.resolve')
            @unless($ticket->isClosed())
                <x-card title="Resolve">
                    <form method="POST" action="{{ route('tickets.resolve', $ticket) }}" class="space-y-2 p-4">
                        @csrf
                        <textarea name="resolution" rows="4" required placeholder="What fixed it?"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ $ticket->resolution }}</textarea>
                        <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">Mark resolved</button>
                    </form>
                </x-card>
            @endunless
        @endpermission

        {{-- Feedback is only offered to the requester once work is done. --}}
        @if(auth()->id() === (int) $ticket->requester_id && $ticket->resolved_at && ! $ticket->satisfaction_rating)
            <x-card title="Rate this resolution">
                <form method="POST" action="{{ route('tickets.rate', $ticket) }}" class="space-y-2 p-4">
                    @csrf
                    <select name="satisfaction_rating" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @foreach([5 => 'Excellent', 4 => 'Good', 3 => 'Acceptable', 2 => 'Poor', 1 => 'Very poor'] as $value => $label)
                            <option value="{{ $value }}">{{ $value }} — {{ $label }}</option>
                        @endforeach
                    </select>
                    <input name="satisfaction_note" placeholder="Comments (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Submit feedback</button>
                </form>
            </x-card>
        @endif
    </div>
</div>

@endsection
