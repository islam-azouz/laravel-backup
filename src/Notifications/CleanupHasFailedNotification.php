<?php

namespace TenancyTools\TenantBackup\Notifications;

use TenancyTools\TenantBackup\Notifications\Concerns\CustomizesBackupNotificationFields;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification as BaseNotification;

class CleanupHasFailedNotification extends BaseNotification
{
    use CustomizesBackupNotificationFields;
}
