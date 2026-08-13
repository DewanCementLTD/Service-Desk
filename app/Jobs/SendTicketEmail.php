<?php

namespace App\Jobs;

use App\Mail\TicketMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one ticket lifecycle email to a fixed set of recipients. Kept off the
 * request itself — see NotificationService::push() for why — and each address
 * is sent to (and failed) independently so one bad mailbox never drops the rest.
 */
class SendTicketEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param  array<int, array{email: string, name: ?string}>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $ticketId,
        public string $event,
        public ?int $actorId,
        public array $recipients,
        public array $context = [],
    ) {
    }

    public function handle(): void
    {
        $ticket = Ticket::find($this->ticketId);

        if (! $ticket || $this->recipients === []) {
            return;
        }

        $actor = $this->actorId ? User::find($this->actorId) : null;

        foreach ($this->recipients as $recipient) {
            try {
                // A fresh Mailable per recipient — TicketMail::headers() sets a
                // Message-ID that Symfony finalises on first send, so reusing one
                // instance across recipients throws RfcComplianceException on the
                // second send onward (silently dropping every recipient but the
                // first, since that throw was being caught right here).
                $mail = new TicketMail($ticket, $this->event, $actor, $this->context);
                Mail::to($recipient['email'], $recipient['name'] ?? null)->send($mail);
            } catch (\Throwable $e) {
                Log::warning('Ticket email failed to send', [
                    'ticket_id' => $this->ticketId,
                    'event'     => $this->event,
                    'recipient' => $recipient['email'],
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
}
