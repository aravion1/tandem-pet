<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuditLogger
{
    /** @param array<string, scalar|null> $payload */
    public function record(?string $actorId, string $action, string $entityType, ?string $entityId, array $payload = []): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'actor_user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
