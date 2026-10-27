<?php

namespace App\Mail;

use App\Models\DevelopmentRequest;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One development-request lifecycle event (created, status/approval changed,
 * or a progress update posted). Mirrors TicketMail — same threading approach,
 * same recipient-per-send rule (see SendDevelopmentRequestEmail) — but for the
 * "New Development Request" flow rather than the bug/issue one.
 */
class DevelopmentRequestMail extends Mailable
{
    public const CREATED        = 'CREATED';
    public const STATUS_CHANGED = 'STATUS_CHANGED';
    public const COMMENTED      = 'COMMENTED';

    /**
     * @param  array<string, mixed>  $context  Event-specific extras: for
     *   STATUS_CHANGED: from_status, to_status, note; for COMMENTED: body.
     */
    public function __construct(
        public DevelopmentRequest $developmentRequest,
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
            view: 'emails.development',
            with: [
                'request' => $this->developmentRequest,
                'event'   => $this->event,
                'actor'   => $this->actor,
                'context' => $this->context,
            ],
        );
    }

    public function headers(): Headers
    {
        $domain = config('servicedesk.mail_thread_domain');

        $rootId    = "development-{$this->developmentRequest->id}@{$domain}";
        $messageId = $this->event === self::CREATED
            ? $rootId
            : sprintf('development-%d-%s-%d@%s', $this->developmentRequest->id, strtolower($this->event), time(), $domain);

        return new Headers(
            messageId: $messageId,
            references: $this->event === self::CREATED ? [] : [$rootId],
            text: $this->event === self::CREATED ? [] : ['In-Reply-To' => "<{$rootId}>"],
        );
    }

    private function subjectLine(): string
    {
        $no = $this->developmentRequest->request_no;

        return match ($this->event) {
            self::CREATED        => "New development request {$no}: {$this->developmentRequest->title}",
            self::STATUS_CHANGED => "Request {$no} is now {$this->context['to_status']} — {$this->developmentRequest->title}",
            self::COMMENTED      => "New update on {$no}: {$this->developmentRequest->title}",
            default               => "Development request {$no}: {$this->developmentRequest->title}",
        };
    }
}
