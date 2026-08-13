<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Delivers in-app notifications to the browsers a user has subscribed with.
 *
 * Push is best-effort: the notification row is the record of truth, and a push
 * that fails must never break the request that created it. Subscriptions the
 * push service rejects (expired, unsubscribed) are pruned as we go.
 */
class WebPushService
{
    public function configured(): bool
    {
        return (bool) config('webpush.enabled')
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return $this->configured() ? config('webpush.vapid.public_key') : null;
    }

    /** Push one stored notification to every browser its owner has registered. */
    public function send(Notification $notification): void
    {
        $this->sendToUser((int) $notification->user_id, [
            'id'      => $notification->id,
            'title'   => $notification->title,
            'body'    => (string) $notification->message,
            'url'     => $notification->url ?: route('notifications.index'),
            'type'    => $notification->notify_type,
            'tag'     => 'sd-notification-'.$notification->id,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function sendToUser(int $userId, array $payload): void
    {
        if (! $this->configured()) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject'    => config('webpush.vapid.subject'),
                'publicKey'  => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ]], ['TTL' => config('webpush.ttl')]);
        } catch (Throwable $e) {
            Log::warning('Web push disabled: '.$e->getMessage());

            return;
        }

        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription;

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $subscription->endpoint,
                    'publicKey'       => $subscription->public_key,
                    'authToken'       => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
                ]),
                json_encode($payload, JSON_UNESCAPED_SLASHES)
            );
        }

        try {
            foreach ($webPush->flush() as $report) {
                $subscription = $byEndpoint[$report->getRequest()->getUri()->__toString()] ?? null;

                if (! $subscription) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $subscription->update(['failure_count' => 0, 'last_used_at' => now()]);

                    continue;
                }

                // 404/410 mean the browser threw the subscription away; anything
                // else gets a few chances before we do the same.
                if ($report->isSubscriptionExpired()
                    || $subscription->failure_count + 1 >= config('webpush.max_failures')) {
                    $subscription->delete();

                    continue;
                }

                $subscription->increment('failure_count');
            }
        } catch (Throwable $e) {
            Log::warning('Web push delivery failed: '.$e->getMessage());
        }
    }
}
