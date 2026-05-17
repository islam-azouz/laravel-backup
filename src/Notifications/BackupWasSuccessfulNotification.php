<?php

namespace TenancyTools\TenantBackup\Notifications;

use TenancyTools\TenantBackup\Notifications\Concerns\CustomizesBackupNotificationFields;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification as BaseNotification;

class BackupWasSuccessfulNotification extends BaseNotification
{
    use CustomizesBackupNotificationFields;
}
