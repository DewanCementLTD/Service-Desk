@extends('layouts.app')

@section('title', 'Report a problem')
@section('subtitle', 'Bugs and issues. The response deadline is set automatically from the priority you choose.')

@section('content')

@php $requester = auth()->user(); @endphp

<form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="max-w-4xl space-y-4">
    @csrf

    {{-- The ticket is raised against the company currently in scope. --}}
    <input type="hidden" name="company_id" value="{{ $activeCompany?->id }}">
    <input type="hidden" name="location_id" value="{{ $activeLocation?->id }}">

    <x-card title="What has gone wrong?">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Subject" name="subject" :required="true" class="md:col-span-2"
                     help="One line, as you would say it out loud — “Cannot post an invoice in ERP”.">
                <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Description" name="description" :required="true" class="md:col-span-2"
                     help="What you were doing, what you expected to happen, and any error message you saw.">
                <textarea id="description" name="description" rows="6" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('description') }}</textarea>
            </x-field>

            <x-field label="Category" name="category_id" :required="true">
                <select id="category_id" name="category_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">Select a category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Priority" name="priority_id" :required="true">
                <select id="priority_id" name="priority_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    @foreach($priorities as $priority)
                        <option value="{{ $priority->id }}" @selected(old('priority_id', $priorities->firstWhere('code', 'MEDIUM')?->id) == $priority->id)>
                            {{ $priority->name }} — respond {{ $priority->response_hours }}h / resolve {{ $priority->resolution_hours }}h
                        </option>
                    @endforeach
                </select>
            </x-field>

            {{-- Taken from the requester's own record: which department a person
                 belongs to is not theirs to choose, and picking the wrong one sent
                 tickets to the wrong queue. --}}
            <x-field label="Your department" name="department_id"
                     help="{{ $requester->department ? 'Taken from your profile. Ask IT if this is wrong.' : 'No department on file — your ticket will still reach IT.' }}">
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                    {{ $requester->department?->name ?? 'Not set' }}
                </p>
            </x-field>

            @permission('tickets.assign')
                <x-field label="Assign to" name="assigned_to" help="Leave empty to send it to the IT queue.">
                    <select id="assigned_to" name="assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        <option value="">Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected(old('assigned_to') == $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endpermission

            <x-field label="Attachments" name="attachments" help="Up to 5 files, 10 MB each.">
                <input id="attachments" type="file" name="attachments[]" multiple
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs dark:border-slate-700 dark:bg-slate-800">
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Submit ticket</button>
        <a href="{{ route('tickets.new') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Back</a>
    </div>
</form>

@endsection
