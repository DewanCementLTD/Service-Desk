@props(['message' => 'Nothing to show yet.', 'colspan' => null])

@if($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
            {{ $message }}
        </td>
    </tr>
@else
    <div class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ $message }}
    </div>
@endif
