<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function __construct(private ScopeContext $scope)
    {
    }

    public function notify(
        int $userId,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $url = null,
        string $type = 'INFO'
    ): ?Notification {
        // Never notify a user about their own action.
        if ($userId === auth()->id()) {
            return null;
        }

        $notification = Notification::create([
            'user_id'     => $userId,
            'company_id'  => $this->scope->companyId(),
            'title'       => mb_substr($title, 0, 200),
            'message'     => $message,
            'notify_type' => $type,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'url'         => $url === null ? null : mb_substr($url, 0, 255),
            'is_read'     => 0,
        ]);

        $this->push($notification);

        return $notification;
    }

    /**
     * Mirror the notification to the user's browsers. Delivery is best effort —
     * the stored row is what the bell and the notifications page read.
     */
    private function push(Notification $notification): void
    {
        if (! app(WebPushService::class)->configured()) {
            return;
        }

        $job = SendPushNotification::dispatch((int) $notification->id);

        // Without a queue worker the job would otherwise run mid-request.
        if (config('queue.default') === 'sync') {
            $job->afterResponse();
        }
    }

    /** @param  iterable<int>  $userIds */
    public function notifyMany(
        iterable $userIds,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $url = null,
        string $type = 'INFO'
    ): void {
        foreach (array_unique(array_filter((array) $userIds)) as $userId) {
            $this->notify((int) $userId, $title, $message, $entityType, $entityId, $url, $type);
        }
    }

    /** Notify every active user holding one of the given role codes in a company. */
    public function notifyRoles(
        array $roleCodes,
        int $companyId,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $url = null
    ): void {
        $userIds = User::active()
            ->whereHas('role', fn ($q) => $q->whereIn('code', $roleCodes))
            ->where(function ($q) use ($companyId) {
                $q->where('is_all_companies', 1)
                    ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId));
            })
            ->pluck('id');

        $this->notifyMany($userIds, $title, $message, $entityType, $entityId, $url);
    }

    public function markAsRead(Notification $notification): void
    {
        if (! $notification->is_read) {
            $notification->update(['is_read' => 1, 'read_at' => now()]);
        }
    }

    public function markAllRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => now()]);
    }

    public function unreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)->where('is_read', 0)->count();
    }
}
