<?php

namespace TenancyTools\TenantBackup\Notifications;

use TenancyTools\TenantBackup\Notifications\Concerns\CustomizesBackupNotificationFields;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification as BaseNotification;

class CleanupWasSuccessfulNotification extends BaseNotification
{
    use CustomizesBackupNotificationFields;
}
