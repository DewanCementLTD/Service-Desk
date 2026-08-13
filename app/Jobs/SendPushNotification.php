<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushing to a browser means several outbound HTTPS calls, so it is kept off
 * the request that created the notification. With the sync queue driver the
 * dispatcher runs it after the response has been sent.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(WebPushService $push): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification) {
            $push->send($notification);
        }
    }
}
