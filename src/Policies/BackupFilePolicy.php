<?php

namespace TenancyTools\TenantBackup\Policies;

class BackupFilePolicy
{
    public function read($user, $model = null): bool
    {
        return $this->allows($user, $this->permission('read', 'read Backup'));
    }

    public function viewAny($user): bool
    {
        return $this->read($user);
    }

    public function create($user, $model = null): bool
    {
        return $this->allows($user, $this->permission('create', 'create Backup'));
    }

    public function delete($user, $model = null): bool
    {
        return $this->allows($user, $this->permission('delete', 'delete Backup'));
    }

    public function restore($user, $model = null): bool
    {
        return $this->allows($user, $this->permission('restore', 'restore Backup'));
    }

    public function update($user, $model = null): bool
    {
        return false;
    }

    private function allows($user, string $permission): bool
    {
        if (!is_object($user)) {
            return false;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return (bool) $user->hasPermissionTo($permission);
            } catch (\Throwable $exception) {
                return false;
            }
        }

        return true;
    }

    private function permission(string $key, string $default): string
    {
        $value = config('tenant-backup.permissions.' . $key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}