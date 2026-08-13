@extends('layouts.app')

@section('title', 'New User')

@section('content')

<form method="POST" action="{{ route('users.store') }}" class="max-w-5xl space-y-4">
    @csrf
    @include('users._form', ['user' => null])

    <div class="flex items-center gap-2">
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create user</button>
        <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</a>
    </div>
</form>

@endsection
