<?php

namespace TenancyTools\TenantBackup\Notifications;

use TenancyTools\TenantBackup\Notifications\Concerns\CustomizesBackupNotificationFields;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification as BaseNotification;

class BackupHasFailedNotification extends BaseNotification
{
    use CustomizesBackupNotificationFields;
}
