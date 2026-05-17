<?php

namespace TenancyTools\TenantBackup\Http\Middleware;

use Closure;
use TenancyTools\TenantBackup\Support\BackupHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInBackupRestoreMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!BackupHelper::isInRestoreMode()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'restore_locked' => true,
                'message' => __('A database restore is in progress. Please wait until it completes.'),
            ], 503);
        }

        return response()->view(config('tenant-backup.restore.lock_view', 'tenant-backup::restore-lock'), [], 503);
    }
}
