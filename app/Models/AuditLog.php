<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends BaseModel
{
    protected $table = 'audit_logs';

    /** Audit rows are immutable. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'company_id', 'location_id', 'action', 'entity_type', 'entity_id',
        'description', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'entity_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** Decoded JSON snapshot, or an empty array when absent/invalid. */
    public function decodedOldValues(): array
    {
        return json_decode((string) $this->old_values, true) ?: [];
    }

    public function decodedNewValues(): array
    {
        return json_decode((string) $this->new_values, true) ?: [];
    }
}
