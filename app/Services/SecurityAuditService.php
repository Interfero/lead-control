<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SecurityAuditService
{
    public function log(
        string $action,
        ?User $user = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $meta = null,
        ?Request $request = null,
    ): void {
        if (! Schema::hasTable('security_audit_logs')) {
            return;
        }

        $request ??= request();

        SecurityAuditLog::query()->create([
            'user_id' => $user?->user_id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512) ?: null,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
