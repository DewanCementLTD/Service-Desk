@extends('layouts.app')

@section('title', 'Notifications')

@section('actions')
    <a href="{{ route('notifications.index', ['filter' => $filter === 'unread' ? 'all' : 'unread']) }}"
       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium dark:border-slate-700">
        {{ $filter === 'unread' ? 'Show all' : 'Show unread only' }}
    </a>
    <form method="POST" action="{{ route('notifications.readAll') }}">
        @csrf
        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-slate-700">Mark all read</button>
    </form>
@endsection

@section('content')

<x-card>
    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($notifications as $notification)
            <li class="{{ $notification->is_read ? '' : 'bg-indigo-50/50 dark:bg-indigo-500/5' }}">
                <a href="{{ route('notifications.open', $notification) }}" class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <div class="flex items-start gap-3">
                        @unless($notification->is_read)
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                        @else
                            <span class="mt-1.5 h-2 w-2 shrink-0"></span>
                        @endunless
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $notification->title }}</p>
                            @if($notification->message)
                                <p class="mt-0.5 truncate text-xs text-slate-600 dark:text-slate-400">{{ $notification->message }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                    </div>
                </a>
            </li>
        @empty
            <x-empty-state message="You have no notifications." />
        @endforelse
    </ul>

    @if($notifications->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $notifications->links() }}</div>
    @endif
</x-card>

@endsection
