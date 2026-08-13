@extends('layouts.app')

@section('title', 'Change password')
@section('subtitle', $forced
    ? 'You must replace the password IT issued you before you can continue.'
    : 'Choose a new password for your account.')

@section('content')

<div class="mx-auto max-w-lg">

    @if($forced)
        <div class="mb-4 flex gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 10a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" />
            </svg>
            <span>
                Your account is still on the starting password everyone was given. Choose your own
                before you use the system — nobody else should know it, including IT.
            </span>
        </div>
    @endif

    <x-card title="Change your password">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4 p-4">
            @csrf
            @method('PUT')

            {{-- Validation errors are rendered by the layout; x-field adds the
                 per-field message underneath each input. --}}

            <x-field label="Current password" name="current_password" :required="true"
                     help="{{ $forced ? 'The one IT gave you.' : null }}">
                <input type="password" name="current_password" required autofocus autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="New password" name="password" :required="true"
                     help="At least 8 characters, with letters and numbers.">
                <input type="password" name="password" required minlength="8" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <x-field label="Confirm new password" name="password_confirmation" :required="true">
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            </x-field>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    Change password
                </button>

                @unless($forced)
                    <a href="{{ route('dashboard') }}"
                       class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                        Cancel
                    </a>
                @endunless
            </div>
        </form>
    </x-card>

    <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
        Pick something you will remember. IT cannot read your password — they can only reset it.
    </p>
</div>

@endsection
