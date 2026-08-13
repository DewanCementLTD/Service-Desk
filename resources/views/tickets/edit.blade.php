@extends('layouts.app')

@section('title', 'Edit ' . $ticket->ticket_no)
@section('subtitle', $ticket->subject)

@section('content')

<form method="POST" action="{{ route('tickets.update', $ticket) }}" class="max-w-4xl space-y-4">
    @csrf
    @method('PUT')

    <x-card title="Ticket details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Subject" name="subject" :required="true" class="md:col-span-2">
                <input id="subject" name="subject" value="{{ old('subject', $ticket->subject) }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Description" name="description" :required="true" class="md:col-span-2">
                <textarea id="description" name="description" rows="6" required
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('description', $ticket->description) }}</textarea>
            </x-field>

            <x-field label="Category" name="category_id" :required="true">
                <select id="category_id" name="category_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $ticket->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Priority" name="priority_id" :required="true">
                <select id="priority_id" name="priority_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    @foreach($priorities as $priority)
                        <option value="{{ $priority->id }}" @selected(old('priority_id', $ticket->priority_id) == $priority->id)>{{ $priority->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Department" name="department_id">
                <select id="department_id" name="department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id', $ticket->department_id) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Location" name="location_id">
                <select id="location_id" name="location_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($availableLocations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $ticket->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
        <a href="{{ route('tickets.show', $ticket) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>

        @permission('tickets.delete')
            <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="ml-auto"
                  onsubmit="return confirm('Delete {{ $ticket->ticket_no }}? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500/40">Delete ticket</button>
            </form>
        @endpermission
    </div>
</form>

@endsection
