<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the immutable audit trail. JSON snapshots go into CLOB columns that
 * carry an IS JSON check constraint, so payloads are always encoded here.
 */
class AuditLogger
{
    public function __construct(private ScopeContext $scope)
    {
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return AuditLog::create([
            'user_id'     => Auth::id(),
            'company_id'  => $this->scope->companyId(),
            'location_id' => $this->scope->locationId(),
            'action'      => strtoupper($action),
            'entity_type' => strtoupper($entityType),
            'entity_id'   => $entityId,
            'description' => $description === null ? null : mb_substr($description, 0, 1000),
            'old_values'  => $this->encode($oldValues),
            'new_values'  => $this->encode($newValues),
            'ip_address'  => Request::ip(),
            'user_agent'  => mb_substr((string) Request::userAgent(), 0, 400),
        ]);
    }

    /** Log a create with the model's own attributes as the new snapshot. */
    public function created(string $entityType, int $entityId, array $attributes, ?string $description = null): void
    {
        $this->log('CREATE', $entityType, $entityId, $description, null, $this->sanitise($attributes));
    }

    /** Log an update, recording only the fields that actually changed. */
    public function updated(
        string $entityType,
        int $entityId,
        array $original,
        array $changes,
        ?string $description = null
    ): void {
        $changes = $this->sanitise($changes);

        if ($changes === []) {
            return;
        }

        $before = array_intersect_key($this->sanitise($original), $changes);

        $this->log('UPDATE', $entityType, $entityId, $description, $before, $changes);
    }

    public function deleted(string $entityType, int $entityId, array $attributes, ?string $description = null): void
    {
        $this->log('DELETE', $entityType, $entityId, $description, $this->sanitise($attributes), null);
    }

    private function encode(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /** Never write credentials or tokens into the audit trail. */
    private function sanitise(array $values): array
    {
        unset($values['password'], $values['remember_token']);

        return array_map(static function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            // Keep CLOB snapshots from bloating the trail.
            if (is_string($value) && mb_strlen($value) > 2000) {
                return mb_substr($value, 0, 2000) . '...';
            }

            return $value;
        }, $values);
    }
}
