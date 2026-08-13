@extends('layouts.app')

@section('title', 'New Development Request')
@section('subtitle', 'Requests go to IT management for approval before development starts.')

@section('content')

@php $requester = auth()->user(); @endphp

<form method="POST" action="{{ route('development.store') }}" class="max-w-4xl space-y-4">
    @csrf

    <x-card title="Request details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Title" name="title" :required="true" class="md:col-span-2">
                <input id="title" name="title" value="{{ old('title') }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Business purpose" name="business_purpose" :required="true" class="md:col-span-2"
                     help="Why is this needed and what problem does it solve?">
                <textarea id="business_purpose" name="business_purpose" rows="4" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('business_purpose') }}</textarea>
            </x-field>

            <x-field label="Development requirements" name="requirements" :required="true" class="md:col-span-2"
                     help="What the system must do, step by step. The approver and the developer work from this.">
                <textarea id="requirements" name="requirements" rows="5" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('requirements') }}</textarea>
            </x-field>

            <x-field label="Expected result" name="expected_benefit" :required="true" class="md:col-span-2"
                     help="How you will know it worked — what you will be able to do that you cannot do today.">
                <textarea id="expected_benefit" name="expected_benefit" rows="3" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('expected_benefit') }}</textarea>
            </x-field>

            <x-field label="Priority" name="priority_id">
                <select id="priority_id" name="priority_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($priorities as $priority)
                        <option value="{{ $priority->id }}" @selected(old('priority_id') == $priority->id)>{{ $priority->name }}</option>
                    @endforeach
                </select>
            </x-field>

            {{-- Taken from the requester's own record, as on the ticket form. --}}
            <x-field label="Your department" name="department_id"
                     help="{{ $requester->department ? 'Taken from your profile.' : 'No department on file — ask IT to set yours.' }}">
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                    {{ $requester->department?->name ?? 'Not set' }}
                </p>
            </x-field>

            <x-field label="Target date" name="target_date">
                <input id="target_date" type="date" name="target_date" value="{{ old('target_date') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Submit request</button>
        <a href="{{ route('tickets.new') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Back</a>
    </div>
</form>

@endsection
