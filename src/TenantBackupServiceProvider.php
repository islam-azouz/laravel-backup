<?php

namespace TenancyTools\TenantBackup;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenantBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tenant-backup.php', 'tenant-backup');

        $mailFromAddress = (string) (config('backup.notifications.mail.from.address')
            ?: config('mail.from.address')
            ?: env('MAIL_FROM_ADDRESS')
            ?: 'hello@example.com');

        $mailFromName = (string) (config('backup.notifications.mail.from.name')
            ?: config('mail.from.name')
            ?: env('MAIL_FROM_NAME')
            ?: config('app.name', 'Laravel'));

        $notificationAddress = (string) (config('backup.notifications.mail.to')
            ?: env('BACKUP_NOTIFICATION_MAIL')
            ?: $mailFromAddress);

        config([
            'backup.notifications.mail.from.address' => $mailFromAddress,
            'backup.notifications.mail.from.name' => $mailFromName,
            'backup.notifications.mail.to' => $notificationAddress,
        ]);
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
