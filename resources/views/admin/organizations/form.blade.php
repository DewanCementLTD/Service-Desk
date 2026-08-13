@extends('layouts.app')

@section('title', $organization->exists ? 'Edit ' . $organization->name : 'New Organisation')

@section('content')

<form method="POST" action="{{ $organization->exists ? route('admin.organizations.update', $organization) : route('admin.organizations.store') }}"
      class="max-w-3xl space-y-4">
    @csrf
    @if($organization->exists) @method('PUT') @endif

    <x-card title="Organisation details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Code" name="code" :required="true" help="Short unique identifier, e.g. GRP.">
                <input name="code" value="{{ old('code', $organization->code) }}" required maxlength="20"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Name" name="name" :required="true">
                <input name="name" value="{{ old('name', $organization->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Legal name" name="legal_name" class="md:col-span-2">
                <input name="legal_name" value="{{ old('legal_name', $organization->legal_name) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Description" name="description" class="md:col-span-2">
                <textarea name="description" rows="3"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('description', $organization->description) }}</textarea>
            </x-field>

            @foreach(['email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'country' => 'Country', 'website' => 'Website', 'timezone' => 'Timezone'] as $field => $label)
                <x-field label="{{ $label }}" name="{{ $field }}">
                    <input name="{{ $field }}" value="{{ old($field, $organization->{$field}) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                </x-field>
            @endforeach

            <x-field label="Address" name="address" class="md:col-span-2">
                <textarea name="address" rows="2"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('address', $organization->address) }}</textarea>
            </x-field>

            <x-field label="Sort order" name="sort_order">
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $organization->sort_order ?? 0) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Status" name="is_active">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization->is_active ?? true)) class="rounded border-slate-300">
                    Active
                </label>
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('admin.organizations.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>
    </div>
</form>

@endsection
