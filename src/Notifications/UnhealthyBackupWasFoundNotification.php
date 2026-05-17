<?php

namespace TenancyTools\TenantBackup\Notifications;

use TenancyTools\TenantBackup\Notifications\Concerns\CustomizesBackupNotificationFields;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification as BaseNotification;

class UnhealthyBackupWasFoundNotification extends BaseNotification
{
    use CustomizesBackupNotificationFields;
}
