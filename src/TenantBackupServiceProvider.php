<?php

namespace TenancyTools\TenantBackup;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenantBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tenant-backup.php', 'tenant-backup');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'tenant-backup');

        Gate::policy(
            'TenancyTools\\TenantBackup\\Models\\BackupFile',
            'TenancyTools\\TenantBackup\\Policies\\BackupFilePolicy'
        );

        $this->publishes([
            __DIR__ . '/../config/tenant-backup.php' => config_path('tenant-backup.php'),
        ], 'tenant-backup-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/tenant-backup'),
        ], 'tenant-backup-views');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'tenant-backup-migrations');
    }
}
