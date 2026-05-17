<?php

use Illuminate\Support\Facades\Route;
use TenancyTools\TenantBackup\Http\Controllers\BackupSettingsController;
use TenancyTools\TenantBackup\Http\Middleware\EnsureNotInBackupRestoreMode;

$middleware = config('tenant-backup.routes.middleware');

if (!is_array($middleware) || empty($middleware)) {
    $middleware = array_values(array_filter([
        'web',
        'auth',
        class_exists(\Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class)
            ? \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class
            : null,
        class_exists(\Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class)
            ? \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class
            : null,
        class_exists(\Stancl\Tenancy\Middleware\ScopeSessions::class)
            ? \Stancl\Tenancy\Middleware\ScopeSessions::class
            : null,
        EnsureNotInBackupRestoreMode::class,
    ]));
}

$prefix = trim((string) config('tenant-backup.routes.prefix', 'settings'), '/');
$namePrefix = (string) config('tenant-backup.routes.name_prefix', 'settings.');

Route::middleware($middleware)
    ->as($namePrefix)
    ->prefix($prefix)
    ->group(function () {
        Route::get('backups/datatable', [BackupSettingsController::class, 'datatable'])->name('backups.datatable');
        Route::get('backups/auto-settings', [BackupSettingsController::class, 'getAutoSettings'])->name('backups.auto-settings.show');
        Route::post('backups/auto-settings', [BackupSettingsController::class, 'saveAutoSettings'])->name('backups.auto-settings.store');
        Route::post('backups/restore/{id}', [BackupSettingsController::class, 'restore'])->name('backups.restore');
        Route::get('backups/restore-status', [BackupSettingsController::class, 'restoreStatus'])
            ->name('backups.restore-status')
            ->withoutMiddleware([EnsureNotInBackupRestoreMode::class, 'auth']);
        Route::get('backups/restore-lock', [BackupSettingsController::class, 'restoreLockView'])
            ->name('backups.restore-lock')
            ->withoutMiddleware([EnsureNotInBackupRestoreMode::class, 'auth']);
        Route::resource('backups', BackupSettingsController::class)->only(['index', 'store', 'destroy']);
    });