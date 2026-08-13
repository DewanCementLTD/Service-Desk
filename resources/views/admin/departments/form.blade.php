@extends('layouts.app')

@section('title', $department->exists ? 'Edit ' . $department->name : 'New Department')

@section('content')

<form method="POST" action="{{ $department->exists ? route('admin.departments.update', $department) : route('admin.departments.store') }}"
      class="max-w-3xl space-y-4">
    @csrf
    @if($department->exists) @method('PUT') @endif

    <x-card title="Department details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Company" name="company_id" :required="true">
                <select name="company_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id', $department->company_id) == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Location" name="location_id">
                <select name="location_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $department->location_id) == $location->id)>
                            {{ $location->company?->code }} · {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Code" name="code" :required="true" help="Unique within the company.">
                <input name="code" value="{{ old('code', $department->code) }}" required maxlength="30"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Name" name="name" :required="true">
                <input name="name" value="{{ old('name', $department->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Head of department" name="head_user_id">
                <select name="head_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">—</option>
                    @foreach($heads as $head)
                        <option value="{{ $head->id }}" @selected(old('head_user_id', $department->head_user_id) == $head->id)>{{ $head->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Email" name="email">
                <input type="email" name="email" value="{{ old('email', $department->email) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Phone" name="phone">
                <input name="phone" value="{{ old('phone', $department->phone) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Sort order" name="sort_order">
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $department->sort_order ?? 0) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Description" name="description" class="md:col-span-2">
                <textarea name="description" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('description', $department->description) }}</textarea>
            </x-field>

            <x-field label="Flags" name="is_active">
                <div class="space-y-1.5">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_it_team" value="1" @checked(old('is_it_team', $department->is_it_team)) class="rounded border-slate-300">
                        IT team — tickets can be routed here
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department->is_active ?? true)) class="rounded border-slate-300">
                        Active
                    </label>
                </div>
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('admin.departments.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>
    </div>
</form>

@endsection
