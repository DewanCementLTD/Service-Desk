<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => Notification::where('user_id', $request->user()->id)
                ->when($request->input('filter') === 'unread', fn ($q) => $q->where('is_read', 0))
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString(),
            'filter' => $request->input('filter', 'all'),
        ]);
    }

    /**
     * Feed behind the header bell: unread count plus the newest few rows.
     * Polled by the layout, so it stays deliberately small.
     */
    public function feed(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $recent = Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json([
            'unread'        => $this->notifications->unreadCount($userId),
            'notifications' => $recent->map(fn (Notification $notification) => [
                'id'      => (int) $notification->id,
                'title'   => $notification->title,
                'message' => $notification->message,
                'type'    => $notification->notify_type,
                'unread'  => ! $notification->is_read,
                'ago'     => $notification->created_at?->diffForHumans(short: true),
                'url'     => route('notifications.open', $notification),
            ]),
        ]);
    }

    /** Mark as read and forward to the linked record. */
    public function open(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === $request->user()->id, 403);

        $this->notifications->markAsRead($notification);

        return redirect($notification->url ?: route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $count = $this->notifications->markAllRead($request->user()->id);

        return back()->with('success', "{$count} notification(s) marked as read.");
    }
}
