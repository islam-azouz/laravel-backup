<?php

namespace TenancyTools\TenantBackup\Notifications\Concerns;

use TenancyTools\TenantBackup\Support\BackupHelper;
use Illuminate\Support\Collection;
use Spatie\Backup\Helpers\Format;

trait CustomizesBackupNotificationFields
{
    protected function backupDestinationProperties(): Collection
    {
        $backupDestination = $this->backupDestination();
        if (!$backupDestination) {
            return collect();
        }

        $backupDestination->fresh();

        $newestBackup = $backupDestination->newestBackup();
        $oldestBackup = $backupDestination->oldestBackup();
        $noBackupsText = trans('backup::notifications.no_backups_info');

        return collect([
            trans('backup::notifications.application_name') => BackupHelper::resolveAccountName(),
            trans('backup::notifications.backup_name') => $newestBackup ? basename($newestBackup->path()) : $noBackupsText,
            trans('backup::notifications.newest_backup_size') => $newestBackup ? Format::humanReadableSize($newestBackup->sizeInBytes()) : $noBackupsText,
            trans('backup::notifications.number_of_backups') => (string) $backupDestination->backups()->count(),
            trans('backup::notifications.total_storage_used') => Format::humanReadableSize($backupDestination->backups()->size()),
            trans('backup::notifications.newest_backup_date') => $newestBackup ? $newestBackup->date()->format('Y/m/d H:i:s') : $noBackupsText,
            trans('backup::notifications.oldest_backup_date') => $oldestBackup ? $oldestBackup->date()->format('Y/m/d H:i:s') : $noBackupsText,
        ])->filter(static fn ($value) => $value !== null && $value !== '');
    }
}
