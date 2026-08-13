<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app notification. Deliberately a plain table rather than Laravel's
 * notifications table so it can carry company scope and an entity reference.
 */
class Notification extends BaseModel
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'company_id', 'title', 'message', 'notify_type',
        'entity_type', 'entity_id', 'url', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read'   => 'boolean',
        'read_at'   => 'datetime',
        'entity_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', 0);
    }
}
