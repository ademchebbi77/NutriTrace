<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record an important action (approval, review, transfer, moderation...).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?Model $subject = null, ?string $description = null, array $metadata = []): AuditLog
    {
        $log = new AuditLog([
            'user_id' => $this->request->user()?->getKey(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => $this->request->ip(),
        ]);

        if ($subject) {
            $log->auditable()->associate($subject);
        }

        $log->save();

        return $log;
    }
}
