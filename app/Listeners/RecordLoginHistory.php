<?php

namespace App\Listeners;

use App\Events\UserAuthenticated;
use App\Models\LoginLog;

/**
 * Writes a row to the legacy `login_log` table for every successful login.
 * Laravel auto-discovers this listener from the type-hinted event below.
 */
class RecordLoginHistory
{
    public function handle(UserAuthenticated $event): void
    {
        LoginLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'login_datetime' => now(),
            'sys_ip' => $event->ip,
            'sys_os' => $event->userAgent,
        ]);
    }
}
