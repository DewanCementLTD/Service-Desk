<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata only: the file itself lives on the configured Laravel disk, which
 * keeps large binaries out of the Oracle tablespace.
 */
class TicketAttachment extends BaseModel
{
    protected $table = 'ticket_attachments';

    protected $fillable = [
        'ticket_id', 'comment_id', 'uploaded_by', 'file_name', 'file_path', 'mime_type', 'file_size',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1) . ' TB';
    }
}
