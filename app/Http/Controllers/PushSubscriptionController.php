<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers and removes the browsers a user wants push notifications on.
 */
class PushSubscriptionController extends Controller
{
    public function __construct(private WebPushService $push)
    {
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->push->configured(), 404);

        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'max:1000', 'url'],
            'keys.p256dh'      => ['required', 'string', 'max:255'],
            'keys.auth'        => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:20'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id'          => $request->user()->id,
                'endpoint'         => $data['endpoint'],
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'       => mb_substr((string) $request->userAgent(), 0, 400),
                'failure_count'    => 0,
                'last_used_at'     => now(),
            ]
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    /** Fires a push at the caller's own browsers so they can check it works. */
    public function test(Request $request): JsonResponse
    {
        abort_unless($this->push->configured(), 404);

        $this->push->sendToUser((int) $request->user()->id, [
            'title' => 'Test notification',
            'body'  => 'Push notifications are working on this browser.',
            'url'   => route('notifications.index'),
            'tag'   => 'sd-test',
        ]);

        return response()->json(['status' => 'sent']);
    }
}
