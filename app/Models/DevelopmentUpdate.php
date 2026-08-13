<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentUpdate extends BaseModel
{
    protected $table = 'development_updates';

    protected $fillable = ['request_id', 'user_id', 'notes', 'progress_percent', 'hours_spent', 'status_id'];

    protected $casts = [
        'progress_percent' => 'integer',
        'hours_spent'      => 'float',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DevelopmentStatus::class, 'status_id');
    }
}
