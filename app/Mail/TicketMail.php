<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One ticket lifecycle event (created, status changed, or commented). All mail
 * for the same ticket carries the same root Message-ID in its References/
 * In-Reply-To headers so it threads as a single conversation in the recipient's
 * mail client — see TicketMailService::threadRootId().
 */
class TicketMail extends Mailable
{
    public const CREATED        = 'CREATED';
    public const STATUS_CHANGED = 'STATUS_CHANGED';
    public const COMMENTED      = 'COMMENTED';

    /**
     * @param  array<string, mixed>  $context  Event-specific extras: for
     *   STATUS_CHANGED: from_status, to_status, note; for COMMENTED: body.
     */
    public function __construct(
        public Ticket $ticket,
        public string $event,
        public ?User $actor,
        public array $context = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket',
            with: [
                'ticket'  => $this->ticket,
                'event'   => $this->event,
                'actor'   => $this->actor,
                'context' => $this->context,
            ],
        );
    }

    public function headers(): Headers
    {
        $domain = config('servicedesk.mail_thread_domain');

        // Headers::messageId() and references() both add their own angle
        // brackets (see Headers::referencesString() and Mailable::buildFrom()
        // -> addIdHeader()), so these must stay bracket-free or Symfony's
        // RFC 2822 id validation rejects the doubled-up brackets. In-Reply-To
        // is a plain text header, so it needs the brackets added manually.
        $rootId    = "ticket-{$this->ticket->id}@{$domain}";
        $messageId = $this->event === self::CREATED
            ? $rootId
            : sprintf('ticket-%d-%s-%d@%s', $this->ticket->id, strtolower($this->event), time(), $domain);

        return new Headers(
            messageId: $messageId,
            references: $this->event === self::CREATED ? [] : [$rootId],
            text: $this->event === self::CREATED ? [] : ['In-Reply-To' => "<{$rootId}>"],
        );
    }

    private function subjectLine(): string
    {
        $no = $this->ticket->ticket_no;

        return match ($this->event) {
            // Every ticket raised through the portal is a bug/issue report — new
            // development work goes through DevelopmentRequestMail instead — so
            // the subject says so rather than the generic "New ticket".
            self::CREATED        => "New bug report {$no}: {$this->ticket->subject}",
            self::STATUS_CHANGED => "Ticket {$no} is now {$this->context['to_status']} — {$this->ticket->subject}",
            self::COMMENTED      => "New reply on {$no}: {$this->ticket->subject}",
            default               => "Ticket {$no}: {$this->ticket->subject}",
        };
    }
}
