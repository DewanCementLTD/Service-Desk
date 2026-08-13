<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends BaseModel
{
    protected $table = 'task_comments';

    protected $fillable = ['task_id', 'user_id', 'body', 'progress_percent'];

    protected $casts = ['progress_percent' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
