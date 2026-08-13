@extends('layouts.app')

@section('title', 'Audit Entry #' . $log->id)
@section('subtitle', $log->action . ' on ' . $log->entity_type . ' #' . $log->entity_id)

@section('actions')
    <a href="{{ route('audit.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">Back to trail</a>
@endsection

@section('content')

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">

    <x-card title="Entry">
        <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
            @foreach([
                'When'       => $log->created_at?->format('d M Y H:i:s'),
                'User'       => $log->user?->name ?? 'System',
                'Action'     => $log->action,
                'Entity'     => $log->entity_type . ' #' . $log->entity_id,
                'Company'    => $log->company?->name,
                'IP address' => $log->ip_address,
            ] as $label => $value)
                <div class="flex items-start justify-between gap-3 px-4 py-2">
                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="text-right text-slate-700 dark:text-slate-300">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
        @if($log->description)
            <p class="border-t border-slate-200 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400">{{ $log->description }}</p>
        @endif
    </x-card>

    {{-- JSON snapshots are stored as CLOB with an IS JSON constraint. --}}
    <x-card title="Before">
        <pre class="overflow-x-auto px-4 py-3 text-xs text-slate-700 dark:text-slate-300">{{ $log->old_values ? json_encode($log->decodedOldValues(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
    </x-card>

    <x-card title="After">
        <pre class="overflow-x-auto px-4 py-3 text-xs text-slate-700 dark:text-slate-300">{{ $log->new_values ? json_encode($log->decodedNewValues(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
    </x-card>
</div>

@endsection
