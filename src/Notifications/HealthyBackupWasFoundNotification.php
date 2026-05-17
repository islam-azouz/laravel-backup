<?php

namespace TenancyTools\TenantBackup\Notifications;

use TenancyTools\TenantBackup\Notifications\Concerns\CustomizesBackupNotificationFields;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification as BaseNotification;

class HealthyBackupWasFoundNotification extends BaseNotification
{
    use CustomizesBackupNotificationFields;
}
