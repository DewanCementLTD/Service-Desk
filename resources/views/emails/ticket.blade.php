@php
    // Tailwind-style color names stored on ticket_statuses/ticket_priorities,
    // mapped to email-safe hex pairs (background tint + solid text/border).
    $palette = [
        'blue'   => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'border' => '#bfdbfe'],
        'amber'  => ['bg' => '#fffbeb', 'fg' => '#b45309', 'border' => '#fde68a'],
        'purple' => ['bg' => '#faf5ff', 'fg' => '#7e22ce', 'border' => '#e9d5ff'],
        'green'  => ['bg' => '#f0fdf4', 'fg' => '#15803d', 'border' => '#bbf7d0'],
        'slate'  => ['bg' => '#f8fafc', 'fg' => '#475569', 'border' => '#e2e8f0'],
        'red'    => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'border' => '#fecaca'],
    ];
    $statusColor   = $palette[$ticket->status?->color] ?? $palette['slate'];
    $priorityColor = $palette[$ticket->priority?->color] ?? $palette['slate'];

    $eventMeta = [
        \App\Mail\TicketMail::CREATED        => ['label' => 'New ticket',   'accent' => '#2563eb'],
        \App\Mail\TicketMail::STATUS_CHANGED => ['label' => 'Status update','accent' => '#0f766e'],
        \App\Mail\TicketMail::COMMENTED      => ['label' => 'New reply',    'accent' => '#7c3aed'],
    ][$event] ?? ['label' => 'Ticket update', 'accent' => '#334155'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $ticket->ticket_no }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:'Segoe UI',Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(15,23,42,0.06);">

                    {{-- ------------------------------------------------------------- brand banner --}}
                    {{-- The logo's linework and wordmark are dark navy on a transparent/white
                         backing, so the banner has to stay light — a dark banner swallows it. --}}
                    <tr>
                        <td align="center" style="background:#f8fafc;padding:24px 24px 20px;border-bottom:3px solid #0b1d3a;">
                            <img src="{{ $message->embed(public_path('images/logo-email.png')) }}"
                                 width="150" alt="Yousuf Dewan Companies — IT Service Desk"
                                 style="display:block;width:150px;max-width:150px;height:auto;border:0;">
                        </td>
                    </tr>

                    {{-- ---------------------------------------------------------------- event pill --}}
                    <tr>
                        <td style="padding:24px 28px 0;">
                            <span style="display:inline-block;background:{{ $eventMeta['accent'] }};color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:4px 10px;border-radius:999px;">
                                {{ $eventMeta['label'] }}
                            </span>
                            <p style="margin:12px 0 2px;font-size:12px;color:#64748b;">Ticket {{ $ticket->ticket_no }}</p>
                            <h1 style="margin:0 0 16px;font-size:19px;line-height:1.35;color:#0f172a;">{{ $ticket->subject }}</h1>
                        </td>
                    </tr>

                    {{-- ------------------------------------------------------------- event body --}}
                    <tr>
                        <td style="padding:0 28px;">
                            @if ($event === \App\Mail\TicketMail::CREATED)
                                <p style="margin:0 0 20px;font-size:14px;line-height:1.65;color:#334155;">
                                    A new ticket has been raised{{ $actor ? ' by ' : '' }}@if($actor)<strong>{{ $actor->name }}</strong>@endif. The IT team has been notified and will update this thread as work progresses.
                                </p>
                            @elseif ($event === \App\Mail\TicketMail::STATUS_CHANGED)
                                <p style="margin:0 0 12px;font-size:14px;line-height:1.65;color:#334155;">
                                    Status changed from <strong>{{ $context['from_status'] ?? '—' }}</strong> to <strong>{{ $context['to_status'] ?? '—' }}</strong>{{ $actor ? ' by ' . $actor->name : '' }}.
                                </p>
                                @if (! empty($context['note']))
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                        <tr>
                                            <td style="background:#f8fafc;border-left:3px solid {{ $eventMeta['accent'] }};border-radius:0 6px 6px 0;padding:12px 14px;font-size:14px;line-height:1.6;color:#1e293b;">
                                                {{ $context['note'] }}
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            @elseif ($event === \App\Mail\TicketMail::COMMENTED)
                                <p style="margin:0 0 8px;font-size:13px;color:#64748b;">
                                    {{ $actor?->name ?? 'Someone' }} replied:
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                    <tr>
                                        <td style="background:#f8fafc;border-left:3px solid {{ $eventMeta['accent'] }};border-radius:0 6px 6px 0;padding:12px 14px;font-size:14px;line-height:1.6;color:#1e293b;">
                                            {!! nl2br(e($context['body'] ?? '')) !!}
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- ------------------------------------------------------------ ticket facts --}}
                    <tr>
                        <td style="padding:0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;width:38%;border-bottom:1px solid #e2e8f0;">Status</td>
                                    <td style="padding:14px 16px;font-size:13px;border-bottom:1px solid #e2e8f0;">
                                        <span style="display:inline-block;background:{{ $statusColor['bg'] }};color:{{ $statusColor['fg'] }};border:1px solid {{ $statusColor['border'] }};border-radius:999px;padding:2px 10px;font-weight:600;">{{ $ticket->status?->name ?? '—' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Priority</td>
                                    <td style="padding:14px 16px;font-size:13px;border-bottom:1px solid #e2e8f0;">
                                        <span style="display:inline-block;background:{{ $priorityColor['bg'] }};color:{{ $priorityColor['fg'] }};border:1px solid {{ $priorityColor['border'] }};border-radius:999px;padding:2px 10px;font-weight:600;">{{ $ticket->priority?->name ?? '—' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Requester</td>
                                    <td style="padding:14px 16px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;">
                                        {{ $ticket->requester?->name ?? '—' }}
                                        @if($ticket->requester?->email)
                                            <span style="display:block;color:#64748b;font-size:12px;">{{ $ticket->requester->email }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Company</td>
                                    <td style="padding:14px 16px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;">{{ $ticket->company?->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;{{ $ticket->assignee ? 'border-bottom:1px solid #e2e8f0;' : '' }}">Department</td>
                                    <td style="padding:14px 16px;font-size:13px;color:#1e293b;{{ $ticket->assignee ? 'border-bottom:1px solid #e2e8f0;' : '' }}">{{ $ticket->department?->name ?? '—' }}</td>
                                </tr>
                                @if ($ticket->assignee)
                                    <tr>
                                        <td style="padding:14px 16px;font-size:13px;color:#64748b;">Assigned to</td>
                                        <td style="padding:14px 16px;font-size:13px;color:#1e293b;">{{ $ticket->assignee->name }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- ------------------------------------------------------------------- CTA --}}
                    <tr>
                        <td align="center" style="padding:24px 28px 28px;">
                            <a href="{{ route('tickets.show', $ticket->id) }}"
                               style="display:inline-block;background:#0b1d3a;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:8px;font-size:14px;font-weight:600;">
                                View ticket &rarr;
                            </a>
                        </td>
                    </tr>

                    {{-- ---------------------------------------------------------------- footer --}}
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                            <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#334155;">Yousuf Dewan Companies &middot; IT Service Desk</p>
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                This is an automated message — replies to this address aren't monitored. Reply from within the ticket on the portal to keep this thread going.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
