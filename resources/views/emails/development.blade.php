@php
    // Same email-safe palette as emails/ticket.blade.php.
    $palette = [
        'blue'   => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'border' => '#bfdbfe'],
        'amber'  => ['bg' => '#fffbeb', 'fg' => '#b45309', 'border' => '#fde68a'],
        'purple' => ['bg' => '#faf5ff', 'fg' => '#7e22ce', 'border' => '#e9d5ff'],
        'green'  => ['bg' => '#f0fdf4', 'fg' => '#15803d', 'border' => '#bbf7d0'],
        'slate'  => ['bg' => '#f8fafc', 'fg' => '#475569', 'border' => '#e2e8f0'],
        'red'    => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'border' => '#fecaca'],
    ];
    $statusColor   = $palette[$request->status?->color] ?? $palette['slate'];
    $priorityColor = $palette[$request->priority?->color] ?? $palette['slate'];

    // Every request that reaches this template came from the "New Development
    // Request" flow (see tickets/choose.blade.php) — distinct from the bug/issue
    // flow, which uses emails/ticket.blade.php and its "New bug report" pill.
    $eventMeta = [
        \App\Mail\DevelopmentRequestMail::CREATED        => ['label' => 'New development request', 'accent' => '#4338ca'],
        \App\Mail\DevelopmentRequestMail::STATUS_CHANGED => ['label' => 'Status update',            'accent' => '#0f766e'],
        \App\Mail\DevelopmentRequestMail::COMMENTED      => ['label' => 'Progress update',           'accent' => '#7c3aed'],
    ][$event] ?? ['label' => 'Request update', 'accent' => '#334155'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $request->request_no }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:'Segoe UI',Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(15,23,42,0.06);">

                    {{-- ------------------------------------------------------------- brand banner --}}
                    <tr>
                        <td style="padding:22px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" valign="middle">
                                        <p style="margin:0;font-size:16px;font-weight:800;letter-spacing:.02em;color:#0b1d3a;">IT Service Desk</p>
                                        <p style="margin:2px 0 0;font-size:11px;font-weight:600;letter-spacing:.03em;color:#94a3b8;">Yousuf Dewan Companies</p>
                                    </td>
                                    <td align="right" valign="middle" width="120">
                                        <img src="{{ $message->embed(public_path('images/logo-email.png')) }}"
                                             width="112" alt="Yousuf Dewan Companies — IT Service Desk"
                                             style="display:block;width:112px;max-width:112px;height:auto;border:0;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ---------------------------------------------------------------- event pill --}}
                    <tr>
                        <td style="padding:24px 28px 0;">
                            <span style="display:inline-block;background:{{ $eventMeta['accent'] }};color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:4px 10px;border-radius:999px;">
                                {{ $eventMeta['label'] }}
                            </span>
                            <p style="margin:14px 0 3px;font-size:12px;color:#64748b;">Request {{ $request->request_no }}</p>
                            <h1 style="margin:0 0 18px;font-size:19px;line-height:1.35;color:#0f172a;">{{ $request->title }}</h1>
                        </td>
                    </tr>

                    {{-- ------------------------------------------------------------- event body --}}
                    <tr>
                        <td style="padding:0 28px;">
                            @if ($event === \App\Mail\DevelopmentRequestMail::CREATED)
                                <p style="margin:0 0 20px;font-size:14px;line-height:1.65;color:#334155;">
                                    A new development request has been raised{{ $actor ? ' by ' : '' }}@if($actor)<strong>{{ $actor->name }}</strong>@endif. It will be reviewed and approved before any work starts.
                                </p>
                            @elseif ($event === \App\Mail\DevelopmentRequestMail::STATUS_CHANGED)
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
                            @elseif ($event === \App\Mail\DevelopmentRequestMail::COMMENTED)
                                <p style="margin:0 0 8px;font-size:13px;color:#64748b;">
                                    {{ $actor?->name ?? 'Someone' }} posted a progress update:
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

                    {{-- ------------------------------------------------------------ request facts --}}
                    <tr>
                        <td style="padding:0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;width:38%;border-bottom:1px solid #e2e8f0;">Status</td>
                                    <td style="padding:14px 16px;font-size:13px;border-bottom:1px solid #e2e8f0;">
                                        <span style="display:inline-block;background:{{ $statusColor['bg'] }};color:{{ $statusColor['fg'] }};border:1px solid {{ $statusColor['border'] }};border-radius:999px;padding:2px 10px;font-weight:600;">{{ $request->status?->name ?? '—' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Priority</td>
                                    <td style="padding:14px 16px;font-size:13px;border-bottom:1px solid #e2e8f0;">
                                        <span style="display:inline-block;background:{{ $priorityColor['bg'] }};color:{{ $priorityColor['fg'] }};border:1px solid {{ $priorityColor['border'] }};border-radius:999px;padding:2px 10px;font-weight:600;">{{ $request->priority?->name ?? '—' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Requester</td>
                                    <td style="padding:14px 16px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;">
                                        {{ $request->requester?->name ?? '—' }}
                                        @if($request->requester?->email)
                                            <span style="display:block;color:#64748b;font-size:12px;">{{ $request->requester->email }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;border-bottom:1px solid #e2e8f0;">Company</td>
                                    <td style="padding:14px 16px;font-size:13px;color:#1e293b;border-bottom:1px solid #e2e8f0;">{{ $request->company?->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;color:#64748b;{{ $request->assignee ? 'border-bottom:1px solid #e2e8f0;' : '' }}">Department</td>
                                    <td style="padding:14px 16px;font-size:13px;color:#1e293b;{{ $request->assignee ? 'border-bottom:1px solid #e2e8f0;' : '' }}">{{ $request->department?->name ?? '—' }}</td>
                                </tr>
                                @if ($request->assignee)
                                    <tr>
                                        <td style="padding:14px 16px;font-size:13px;color:#64748b;">Assigned to</td>
                                        <td style="padding:14px 16px;font-size:13px;color:#1e293b;">{{ $request->assignee->name }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- ------------------------------------------------------------------- CTA --}}
                    <tr>
                        <td align="center" style="padding:24px 28px 28px;">
                            <a href="{{ route('development.show', $request->id) }}"
                               style="display:inline-block;background:#0b1d3a;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:8px;font-size:14px;font-weight:600;">
                                View request &rarr;
                            </a>
                        </td>
                    </tr>

                    {{-- ---------------------------------------------------------------- footer --}}
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                            <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#334155;">Yousuf Dewan Companies &middot; IT Service Desk</p>
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                                This is an automated message — replies to this address aren't monitored. Reply from within the request on the portal to keep this thread going.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
