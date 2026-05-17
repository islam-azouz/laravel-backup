<?php

return [
    'models' => [
        'settings' => 'App\\Models\\Settings',
        'authorization' => 'TenancyTools\\TenantBackup\\Models\\BackupFile',
    ],

    'permissions' => [
        'read' => 'read Backup',
        'create' => 'create Backup',
        'delete' => 'delete Backup',
        'restore' => 'restore Backup',
    ],

    'routes' => [
        'prefix' => 'settings',
        'name_prefix' => 'settings.',
        'middleware' => null,
    ],

    'settings' => [
        'general_type' => 'general_settings',
        'auto_type' => 'backup_settings',
        'company_name_key' => 'company_name',
        'email_key' => 'default_email',
        'data_column' => 'data',
    ],

    'restore' => [
        'lock_view' => 'tenant-backup::restore-lock',
        'cooldown_seconds' => 1,
        'lock_grace_seconds' => 1,
        'central_connection' => 'tenancy.database.central_connection',
    ],

    'artisanal' => [
        'migrate_command' => 'tenants:migrate',
        'data_update_command' => 'tenants:data-update',
        'seed_command' => 'tenants:seed',
        'seed_class' => 'Database\\Seeders\\Tenant\\UpdateDataSeeder',
    ],

    'ui' => [
        'index_view' => 'tenant-backup::index',
        'module_slug' => 'settings/backups',
        'view_folder' => 'settings.backups',
        'route_names' => [
            'store' => 'settings.backups.store',
            'auto_settings_show' => 'settings.backups.auto-settings.show',
            'auto_settings_store' => 'settings.backups.auto-settings.store',
            'restore_lock' => 'settings.backups.restore-lock',
            'restore_status' => 'settings.backups.restore-status',
        ],
        'destroy_url' => 'settings/backups',
        'restore_url' => 'settings/backups/restore',
        'dashboard_route' => 'dashboard',
        'logo_path' => 'assets/media/logos/anevex-logo.png',
    ],
];
