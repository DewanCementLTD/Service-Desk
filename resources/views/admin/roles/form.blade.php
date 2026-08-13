@extends('layouts.app')

@section('title', $role->exists ? 'Edit ' . $role->name : 'New Role')
@section('subtitle', 'Tick what this role may view, create and update. Every user holding the role gets exactly these rights.')

@section('content')

<form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
      class="max-w-5xl space-y-4">
    @csrf
    @if($role->exists) @method('PUT') @endif

    <x-card title="Role details">
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <x-field label="Code" name="code" :required="true"
                     help="{{ $role->is_system ? 'Built-in roles keep their code — the application matches on it.' : 'Letters, numbers and underscores. Used internally, e.g. STORE_MANAGER.' }}">
                <input name="code" value="{{ old('code', $role->code) }}" required maxlength="50"
                       @disabled($role->is_system)
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:disabled:bg-slate-800/60">
            </x-field>

            <x-field label="Name" name="name" :required="true">
                <input name="name" value="{{ old('name', $role->name) }}" required maxlength="100"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Hierarchy level" name="hierarchy_level" :required="true"
                     help="Lower number = more authority. Admin is 1, employee is 50.">
                <input type="number" name="hierarchy_level" min="1" max="99" required
                       value="{{ old('hierarchy_level', $role->hierarchy_level ?? 50) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Sort order" name="sort_order">
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $role->sort_order ?? 0) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Description" name="description" class="md:col-span-2">
                <textarea name="description" rows="2" maxlength="500"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">{{ old('description', $role->description) }}</textarea>
            </x-field>

            <x-field label="Status" name="is_active">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $role->is_active ?? true)) class="rounded border-slate-300">
                    Active — the role can be assigned to users
                </label>
            </x-field>
        </div>
    </x-card>

    <x-card title="Permissions" subtitle="View, create, update and the rest — grouped by module">
        @if($isAdmin)
            <div class="p-4">
                <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    The System Administrator role holds the wildcard grant and always has every permission.
                    Permissions cannot be edited here — create a separate role to hand out limited rights.
                </p>
            </div>
        @else
            @php $checked = old('permissions', $selected); @endphp

            <div class="grid grid-cols-1 gap-px bg-slate-200 dark:bg-slate-800 md:grid-cols-2 xl:grid-cols-3">
                @foreach($modules as $module => $permissions)
                    <div class="bg-white p-4 dark:bg-slate-900" data-permission-group>
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $module }}</h3>
                            <button type="button" data-toggle-group
                                    class="text-[11px] font-medium text-indigo-600 hover:underline dark:text-indigo-400">Toggle all</button>
                        </div>

                        <div class="space-y-1.5">
                            @foreach($permissions as $permission)
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->code }}"
                                           @checked(in_array($permission->code, $checked, true))
                                           class="mt-0.5 rounded border-slate-300">
                                    <span>
                                        <span class="text-slate-800 dark:text-slate-200">{{ $permission->name }}</span>
                                        <span class="block font-mono text-[11px] text-slate-400">{{ $permission->code }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('admin.roles.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>
        @if($role->exists)
            <span class="ml-auto text-xs text-slate-500">{{ $role->users()->count() }} user(s) currently hold this role.</span>
        @endif
    </div>
</form>

<script>
    // "Toggle all" flips a module's boxes to whatever the majority is not.
    document.querySelectorAll('[data-toggle-group]').forEach(function (button) {
        button.addEventListener('click', function () {
            var boxes = button.closest('[data-permission-group]').querySelectorAll('input[type=checkbox]');
            var allOn = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allOn; });
        });
    });
</script>

@endsection
