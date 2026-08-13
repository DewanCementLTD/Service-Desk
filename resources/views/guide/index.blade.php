{{--
    The user guide.

    Deliberately short. GuideController picks the steps for the reader's role, so
    this page renders one guide — never a menu of guides for jobs the reader does
    not do. Anything longer than five steps belongs in a conversation with IT.
--}}
@extends('layouts.app')

@section('title', $guide['title'])
@section('subtitle', 'Written for ' . ($guideUser->role?->name ?? 'your role') . ' — ' . $guideUser->name)

@section('content')

<div class="max-w-3xl space-y-4">

    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $guide['intro'] }}</p>

    {{-- ------------------------------------------------------------- the steps --}}
    <x-card>
        <ol class="divide-y divide-slate-200 dark:divide-slate-800">
            @foreach($guide['steps'] as [$heading, $detail, $linkLabel, $linkRoute])
                <li class="flex gap-4 p-5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-brand-500/20 dark:text-brand-200">
                        {{ $loop->iteration }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $heading }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $detail }}</p>

                        @if($linkRoute && Route::has($linkRoute))
                            <a href="{{ route($linkRoute) }}"
                               class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:underline dark:text-brand-300">
                                {{ $linkLabel }}
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg>
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </x-card>

    {{-- ---------------------------------------------------- priorities & SLA --}}
    @permission('tickets.create')
        @if($priorities->isNotEmpty())
            <x-card title="What each priority means"
                    subtitle="Priority sets the deadline, so choose it honestly.">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Priority</th>
                                <th class="px-4 py-2.5 font-semibold">First reply within</th>
                                <th class="px-4 py-2.5 font-semibold">Fixed within</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach($priorities as $priority)
                                <tr>
                                    <td class="px-4 py-2.5"><x-badge :color="$priority->color ?? 'slate'">{{ $priority->name }}</x-badge></td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $priority->response_hours }} hours</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $priority->resolution_hours }} hours</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    @endpermission

    {{-- ---------------------------------------------------------- who to ask --}}
    @if($itContacts->isNotEmpty())
        <x-card title="Who to contact" subtitle="The IT team for the company you are viewing.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Name</th>
                            <th class="px-4 py-2.5 font-semibold">Role</th>
                            <th class="px-4 py-2.5 font-semibold">Mobile</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($itContacts->take(8) as $contact)
                            <tr>
                                <td class="px-4 py-2.5 font-medium text-slate-900 dark:text-white">{{ $contact->name }}</td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $contact->role?->name }}</td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $contact->phone ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                Raise a ticket first — it gives your request a number and a deadline. Call only if it is genuinely urgent.
            </p>
        </x-card>
    @endif

    {{-- --------------------------------------------------------------- basics --}}
    <x-card title="If you get stuck">
        <div class="divide-y divide-slate-200 dark:divide-slate-800">
            @foreach([
                ['I cannot see something a colleague mentioned.',
                 'You see the company and location selected in the boxes at the top of the screen, and — unless your role covers the whole queue — only the tickets you raised or were given. Switch the company box back if something vanished; nothing is ever lost.'],
                ['I forgot my password.',
                 'There is no self-service reset. Contact IT and they will issue you a temporary one that you replace when you sign in.'],
                ['My request has not been picked up.',
                 'Comment on it — whoever holds it is notified straight away. The coloured SLA label shows how much of the deadline is left.'],
            ] as [$question, $answer])
                <details class="group p-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium text-slate-900 dark:text-white">
                        {{ $question }}
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.2 7.5 10 12.3l4.8-4.8 1.4 1.4-6.2 6.2-6.2-6.2 1.4-1.4z" clip-rule="evenodd" />
                        </svg>
                    </summary>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </x-card>
</div>

@endsection
