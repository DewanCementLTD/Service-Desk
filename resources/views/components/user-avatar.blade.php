{{-- A person's photo, falling back to their initials when none is uploaded. --}}
@props(['user', 'class' => 'h-8 w-8', 'text' => 'text-xs'])

@if($user?->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => $class . ' shrink-0 rounded-full object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => $class . ' ' . $text . ' flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-200']) }}>
        {{ $user?->initials ?? 'U' }}
    </span>
@endif
