<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an audit event
     *
     * @param string $action
     * @param array $data
     * @return void
     */
    public static function log(string $action, array $data = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $data['user_id'] ?? Auth::id(),
                'action' => $action,
                'data' => json_encode($data),
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
            ]);
        } catch (\Exception $e) {
            // Silently fail - audit logging should not break the application
            \Log::error('Audit logging failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
