<?php

namespace App\Jobs;

use App\Mail\DevelopmentRequestMail;
use App\Models\DevelopmentRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one development-request lifecycle email to a fixed set of recipients.
 * Mirrors SendTicketEmail — see there for why this is queued and why a fresh
 * Mailable is built per recipient.
 */
class SendDevelopmentRequestEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param  array<int, array{email: string, name: ?string}>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $developmentRequestId,
        public string $event,
        public ?int $actorId,
        public array $recipients,
        public array $context = [],
    ) {
    }

    public function handle(): void
    {
        $developmentRequest = DevelopmentRequest::find($this->developmentRequestId);

        if (! $developmentRequest || $this->recipients === []) {
            return;
        }

        $actor = $this->actorId ? User::find($this->actorId) : null;

        foreach ($this->recipients as $recipient) {
            try {
                $mail = new DevelopmentRequestMail($developmentRequest, $this->event, $actor, $this->context);
                Mail::to($recipient['email'], $recipient['name'] ?? null)->send($mail);
            } catch (\Throwable $e) {
                Log::warning('Development request email failed to send', [
                    'development_request_id' => $this->developmentRequestId,
                    'event'                  => $this->event,
                    'recipient'               => $recipient['email'],
                    'error'                   => $e->getMessage(),
                ]);
            }
        }
    }
}
