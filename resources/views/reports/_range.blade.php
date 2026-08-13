{{-- Shared date-range filter for the report screens. --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
    <x-field label="From" name="from">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
    </x-field>
    <x-field label="To" name="to">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
    </x-field>
    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white dark:bg-slate-700">Apply</button>
</form>
