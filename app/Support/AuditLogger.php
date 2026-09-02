<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $action, string $description, ?Model $subject = null, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
            'request_id' => request()->attributes->get('request_id'),
            'user_agent' => str(request()->userAgent())->limit(1000)->toString(),
        ]);
    }
}
