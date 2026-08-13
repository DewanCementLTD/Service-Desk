<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One browser that has been granted permission to show this user's
 * notifications. A user may have several (office desktop, laptop, phone).
 */
class PushSubscription extends BaseModel
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token',
        'content_encoding', 'user_agent', 'failure_count', 'last_used_at',
    ];

    protected $casts = [
        'failure_count' => 'integer',
        'last_used_at'  => 'datetime',
    ];

    /** Endpoints are too long to index on Oracle, so uniqueness rides on a hash. */
    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
