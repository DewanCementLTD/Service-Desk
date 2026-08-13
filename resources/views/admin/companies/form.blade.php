@extends('layouts.app')

@section('title', $company->exists ? 'Edit ' . $company->name : 'New Company')

@section('content')

<form method="POST" action="{{ $company->exists ? route('admin.companies.update', $company) : route('admin.companies.store') }}"
      class="max-w-3xl space-y-4">
    @csrf
    @if($company->exists) @method('PUT') @endif

    <x-card title="Company details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Organisation" name="organization_id" :required="true">
                <select name="organization_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                    @foreach($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected(old('organization_id', $company->organization_id) == $organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Code" name="code" :required="true" help="Unique within the organisation.">
                <input name="code" value="{{ old('code', $company->code) }}" required maxlength="20"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Name" name="name" :required="true">
                <input name="name" value="{{ old('name', $company->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Legal name" name="legal_name">
                <input name="legal_name" value="{{ old('legal_name', $company->legal_name) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Ticket prefix" name="ticket_prefix" :required="true"
                     help="Drives this company's ticket series, e.g. IT-2026-000001.">
                <input name="ticket_prefix" value="{{ old('ticket_prefix', $company->ticket_prefix) }}" required maxlength="10"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Currency code" name="currency_code">
                <input name="currency_code" value="{{ old('currency_code', $company->currency_code) }}" maxlength="3"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            @foreach(['email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'country' => 'Country', 'timezone' => 'Timezone'] as $field => $label)
                <x-field label="{{ $label }}" name="{{ $field }}">
                    <input name="{{ $field }}" value="{{ old($field, $company->{$field}) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                </x-field>
            @endforeach

            <x-field label="Sort order" name="sort_order">
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $company->sort_order ?? 0) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Address" name="address" class="md:col-span-2">
                <textarea name="address" rows="2"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('address', $company->address) }}</textarea>
            </x-field>

            <x-field label="Status" name="is_active">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $company->is_active ?? true)) class="rounded border-slate-300">
                    Active
                </label>
            </x-field>
        </div>
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('admin.companies.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>
    </div>
</form>

@endsection
