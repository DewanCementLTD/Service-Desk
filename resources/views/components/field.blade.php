@props(['label' => null, 'name' => null, 'required' => false, 'help' => null])

<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if($label)
        <label @if($name) for="{{ $name }}" @endif
            class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($help)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $help }}</p>
    @endif

    @if($name)
        @error($name)
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    @endif
</div>
