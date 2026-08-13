@extends('layouts.app')

@section('title', 'Edit ' . $user->name)
@section('subtitle', $user->email)

@section('content')

<form method="POST" action="{{ route('users.update', $user) }}" class="max-w-5xl space-y-4">
    @csrf
    @method('PUT')
    @include('users._form')

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
        <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Back to list</a>
    </div>
</form>

@if($user->id !== auth()->id())
    {{-- Accounts are deactivated rather than deleted so history stays intact. --}}
    <form method="POST" action="{{ route('users.toggle', $user) }}" class="mt-4 max-w-5xl"
          onsubmit="return confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->name }}?');">
        @csrf
        <button type="submit" class="rounded-lg border px-4 py-2 text-sm font-medium {{ $user->is_active ? 'border-red-300 text-red-600 hover:bg-red-50 dark:border-red-500/40' : 'border-green-300 text-green-700 hover:bg-green-50 dark:border-green-500/40' }}">
            {{ $user->is_active ? 'Deactivate account' : 'Activate account' }}
        </button>
    </form>
@endif

@endsection
