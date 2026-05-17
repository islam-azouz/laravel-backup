<?php

namespace TenancyTools\TenantBackup\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupHelper
{
    public const MANIFEST_FILE = 'backup-manifest.json';
    public const AUTO_BACKUP_MARKER = 'auto_backup';
    public const AUTO_SETTINGS_DEFAULTS = [
        'enabled' => false,
        'frequency_hours' => 24,
        'start_hour' => 0,
        'end_hour' => 23,
        'last_run_at' => null,
    ];

    public static function startRestoreMode(): void
    {
        self::setRestoreState('in_progress');
    }

    public static function endRestoreMode(): void
    {
        self::setRestoreState(null);
    }

    public static function isInRestoreMode(): bool
    {
        $id = self::currentTenantId();
        if ($id === null) {
            return false;
        }

        try {
            return self::tenantsTable()->where('id', $id)->value('restore_state') === 'in_progress';
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private static function setRestoreState(?string $state): void
    {
        $id = self::currentTenantId();
        if ($id === null) {
            return;
        }

        try {
            self::tenantsTable()->where('id', $id)->update(['restore_state' => $state]);
        } catch (\Throwable $exception) {
            Log::warning('Could not update tenants.restore_state', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function tenantsTable()
    {
        return DB::connection(config(config('tenant-backup.restore.central_connection', 'tenancy.database.central_connection')))
            ->table('tenants');
    }

    private static function currentTenantId(): ?string
    {
        $current = function_exists('tenant') ? tenant() : null;

        return $current ? (string) $current->getTenantKey() : null;
    }

    public static function destinations(): array
    {
        $name = (string) config('backup.backup.name', 'database-backups');
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $backupDestinationClass = '\\Spatie\\Backup\\BackupDestination\\BackupDestination';

        return array_map(
            static fn (string $disk) => $backupDestinationClass::create($disk, $name),
            $disks
        );
    }

    public static function findBackup(string $diskName, string $path)
    {
        foreach (self::destinations() as $destination) {
            if ($destination->diskName() !== $diskName) {
                continue;
            }

            foreach ($destination->backups() as $backup) {
                if ($backup->path() === $path) {
                    return $backup;
                }
            }
        }

        return null;
    }

    public static function collectAllBackupPaths(): array
    {
        $paths = [];

        foreach (self::destinations() as $destination) {
            foreach ($destination->backups() as $backup) {
                $paths[] = $backup->path();
            }
        }

        return $paths;
    }

    public static function deleteBackupByPath(string $path): bool
    {
        foreach (self::destinations() as $destination) {
            foreach ($destination->backups() as $backup) {
                if ($backup->path() === $path) {
                    $backup->delete();
                    return true;
                }
            }
        }

        return false;
    }

    public static function configureSpatieForCurrentConnection(): void
    {
        $connectionName = config('backup.backup.source.databases.0', 'tenant');
        $dumpKey = "database.connections.$connectionName.dump";

        if (!config($dumpKey)) {
            $fallback = config('database.connections.mysql.dump');
            if (is_array($fallback)) {
                config([$dumpKey => $fallback]);
            }
        }

        $tempRoot = storage_path('app/backup-temp');
        if (is_dir($tempRoot)) {
            $threshold = time() - 3600;
            foreach (@glob($tempRoot . '/run_*') ?: [] as $stale) {
                if (@filemtime($stale) < $threshold) {
                    self::deleteDirectoryRecursive($stale);
                }
            }
        }

        $tempDir = $tempRoot . '/' . uniqid('run_', true);
        config(['backup.backup.temporary_directory' => $tempDir]);

        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tenantEmail = self::resolveNotificationRecipient();
        $configEmail = config('backup.notifications.mail.to')
            ?: env('BACKUP_NOTIFICATION_MAIL', env('MAIL_FROM_ADDRESS'));

        $recipients = collect([$configEmail, $tenantEmail])
            ->flatMap(static fn ($value) => is_array($value) ? $value : [$value])
            ->filter(static fn ($value) => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL))
            ->map(static fn ($value) => strtolower(trim($value)))
            ->unique()
            ->values()
            ->all();

        if (!empty($recipients)) {
            config(['backup.notifications.mail.to' => count($recipients) === 1 ? $recipients[0] : $recipients]);
        }
    }

    public static function encodeId(string $diskName, string $path): string
    {
        return base64_encode($diskName . '|' . $path);
    }

    public static function parseId(string $id): array
    {
        $decoded = base64_decode($id, true);
        if ($decoded === false || !str_contains($decoded, '|')) {
            abort(400, 'Invalid backup id');
        }

        return explode('|', $decoded, 2);
    }

    public static function manifestDisk(): string
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);

        return $disks[0] ?? 'local';
    }

    public static function manifestPath(): string
    {
        $folder = (string) config('backup.backup.name', 'database-backups');

        return trim($folder, '/') . '/' . self::MANIFEST_FILE;
    }

    public static function readManifest(): array
    {
        $disk = self::manifestDisk();
        $path = self::manifestPath();

        if (!Storage::disk($disk)->exists($path)) {
            return [];
        }

        $data = json_decode(Storage::disk($disk)->get($path), true);

        return is_array($data) ? $data : [];
    }

    public static function writeManifest(array $data): void
    {
        Storage::disk(self::manifestDisk())->put(
            self::manifestPath(),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function extractSqlFromBackup(string $diskName, string $path): string
    {
        $disk = Storage::disk($diskName);
        $tempBase = storage_path('app/restore-temp');

        if (!is_dir($tempBase)) {
            @mkdir($tempBase, 0775, true);
        }

        $threshold = time() - 3600;
        foreach (@glob($tempBase . '/bk_*') ?: [] as $stale) {
            if (@filemtime($stale) < $threshold) {
                @unlink($stale);
            }
        }

        $sourceZip = method_exists($disk, 'path') ? $disk->path($path) : null;
        $tempZip = null;

        if (!$sourceZip || !is_file($sourceZip)) {
            $tempZip = tempnam($tempBase, 'bk_zip_');
            file_put_contents($tempZip, $disk->get($path));
            $sourceZip = $tempZip;
        }

        $zip = new ZipArchive();
        if ($zip->open($sourceZip) !== true) {
            if ($tempZip) {
                @unlink($tempZip);
            }

            throw new \RuntimeException('Could not open backup archive');
        }

        $sqlEntry = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (str_ends_with(strtolower($name), '.sql')) {
                $sqlEntry = $name;
                break;
            }
        }

        if (!$sqlEntry) {
            $zip->close();
            if ($tempZip) {
                @unlink($tempZip);
            }

            throw new \RuntimeException('No SQL dump found inside the backup archive');
        }

        $sqlOut = tempnam($tempBase, 'bk_sql_');
        $stream = $zip->getStream($sqlEntry);
        if (!$stream) {
            $zip->close();
            if ($tempZip) {
                @unlink($tempZip);
            }
            @unlink($sqlOut);

            throw new \RuntimeException('Could not read SQL file from archive');
        }

        $out = fopen($sqlOut, 'wb');
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $zip->close();

        if ($tempZip) {
            @unlink($tempZip);
        }

        return $sqlOut;
    }

    public static function runSqlFile(string $sqlPath): void
    {
        $config = self::currentMysqlConfig();
        $binary = self::resolveMysqlBinary();

        self::dropAllTablesInCurrentDatabase();

        $command = array_filter([
            $binary,
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['username'],
            $config['password'] !== null && $config['password'] !== '' ? '--password=' . $config['password'] : null,
            '--default-character-set=' . ($config['charset'] ?? 'utf8mb4'),
            $config['database'],
        ]);

        $process = new Process($command);
        $process->setTimeout(60 * 30);
        $process->setInput(fopen($sqlPath, 'rb'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysql restore failed');
        }
    }

    private static function dropAllTablesInCurrentDatabase(): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $tables = $connection->select(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ?',
            [$database]
        );

        if (empty($tables)) {
            return;
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $row) {
                $name = $row->name ?? $row->NAME ?? null;
                if ($name) {
                    $connection->statement('DROP TABLE IF EXISTS `' . $name . '`');
                }
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public static function currentMysqlConfig(): array
    {
        $connection = DB::connection();
        $name = $connection->getName();
        $config = config("database.connections.$name");

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new \RuntimeException('Restore is only supported for MySQL connections.');
        }

        return [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 3306,
            'database' => $connection->getDatabaseName(),
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
        ];
    }

    public static function resolveMysqlBinary(): string
    {
        $candidates = [];
        $dumpDir = config('database.connections.' . DB::connection()->getName() . '.dump.dump_binary_path');
        if (is_string($dumpDir) && $dumpDir !== '') {
            $candidates[] = rtrim($dumpDir, '/\\') . DIRECTORY_SEPARATOR . 'mysql';
        }

        $envValue = env('MYSQL_PATH');
        if (is_string($envValue) && $envValue !== '') {
            $candidates[] = $envValue;
        }

        $candidates = array_merge($candidates, [
            '/Applications/XAMPP/xamppfiles/bin/mysql',
            '/Applications/MAMP/Library/bin/mysql',
            '/opt/homebrew/bin/mysql',
            '/opt/homebrew/opt/mysql-client/bin/mysql',
            '/usr/local/opt/mysql-client/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            '/usr/local/bin/mysql',
            '/usr/bin/mysql',
        ]);

        foreach ($candidates as $candidate) {
            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = @shell_exec('command -v mysql 2>/dev/null');
        if (is_string($which)) {
            $which = trim($which);
            if ($which !== '' && @is_executable($which)) {
                return $which;
            }
        }

        throw new \RuntimeException("Could not locate the 'mysql' binary. Set MYSQL_PATH in your .env to its absolute path.");
    }

    public static function runTenantMigrationsForCurrentTenant(): void
    {
        self::runTenantScopedCommand(
            self::configString('tenant-backup.artisanal.migrate_command', 'tenants:migrate'),
            ['--force' => true],
            'Tenant migrations after restore'
        );
    }

    public static function runTenantDataUpdatesForCurrentTenant(): void
    {
        self::runTenantScopedCommand(
            self::configString('tenant-backup.artisanal.data_update_command', 'tenants:data-update'),
            [],
            'Tenant data-updates after restore'
        );
    }

    public static function runUpdateDataSeederForCurrentTenant(): void
    {
        self::runTenantScopedCommand(
            self::configString('tenant-backup.artisanal.seed_command', 'tenants:seed'),
            [
                '--class' => self::configString('tenant-backup.artisanal.seed_class', 'Database\\Seeders\\Tenant\\UpdateDataSeeder'),
                '--force' => true,
            ],
            'UpdateDataSeeder after restore'
        );
    }

    public static function getAutoSettings(): array
    {
        $stored = self::readSettingsPayload(self::configString('tenant-backup.settings.auto_type', 'backup_settings'));

        return array_merge(self::AUTO_SETTINGS_DEFAULTS, $stored);
    }

    public static function saveAutoSettings(array $data): array
    {
        $current = self::getAutoSettings();
        $merged = array_merge($current, $data);
        self::writeSettingsPayload(self::configString('tenant-backup.settings.auto_type', 'backup_settings'), $merged);

        return $merged;
    }

    public static function shouldAutoBackupRunNow(array $settings, ?Carbon $now = null): bool
    {
        if (empty($settings['enabled'])) {
            return false;
        }

        $now = $now ?: Carbon::now();
        $hour = (int) $now->format('G');
        $startHour = (int) ($settings['start_hour'] ?? 0);
        $endHour = (int) ($settings['end_hour'] ?? 23);
        $frequency = max(1, (int) ($settings['frequency_hours'] ?? 1));

        $inWindow = $startHour <= $endHour
            ? ($hour >= $startHour && $hour <= $endHour)
            : ($hour >= $startHour || $hour <= $endHour);

        if (!$inWindow) {
            return false;
        }

        $lastRunAt = $settings['last_run_at'] ?? null;
        if (!$lastRunAt) {
            return true;
        }

        try {
            $last = Carbon::parse($lastRunAt);
        } catch (\Throwable $exception) {
            return true;
        }

        $toleranceMinutes = 5;
        $requiredMinutes = ($frequency * 60) - $toleranceMinutes;

        return abs($now->diffInMinutes($last, false)) >= $requiredMinutes;
    }

    public static function runScheduledBackupIfDue(): bool
    {
        $settings = self::getAutoSettings();
        if (!self::shouldAutoBackupRunNow($settings)) {
            return false;
        }

        $existingPaths = self::collectAllBackupPaths();
        self::configureSpatieForCurrentConnection();

        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
        if ($exitCode !== 0) {
            Log::error('Scheduled backup failed', [
                'tenant' => function_exists('tenant') ? optional(tenant())->getTenantKey() : null,
                'output' => Artisan::output(),
            ]);
            return false;
        }

        $newPaths = array_values(array_diff(self::collectAllBackupPaths(), $existingPaths));
        $createdPath = end($newPaths) ?: null;

        if ($createdPath) {
            $manifest = self::readManifest();
            $manifest[$createdPath] = [
                'description' => '[' . __('Auto Backup') . '] ' . __('Created automatically by the scheduled auto-backup job'),
                'created_by_id' => null,
                'created_by_name' => __('Auto Backup'),
                'source' => self::AUTO_BACKUP_MARKER,
                'created_at' => now()->toDateTimeString(),
            ];
            self::writeManifest($manifest);
            self::pruneBackupsPerConfig($createdPath, true);
        }

        self::saveAutoSettings(['last_run_at' => now()->toDateTimeString()]);

        return true;
    }

    public static function pruneBackupsPerConfig(?string $protectedPath = null, bool $isAuto = false): void
    {
        $manifest = self::readManifest();
        $changed = false;
        $autoKeep = (int) config('backup.app_backup.auto_backup_keep', 3);
        $manualKeep = (int) config('backup.app_backup.manual_backup_keep', 0);

        if ($autoKeep > 0) {
            [$manifest, $changed] = self::pruneGroup(
                $manifest,
                static fn (array $meta) => self::isAutoBackupEntry($meta),
                $autoKeep,
                $isAuto ? $protectedPath : null,
                $changed
            );
        }

        if ($manualKeep > 0) {
            [$manifest, $changed] = self::pruneGroup(
                $manifest,
                static fn (array $meta) => !self::isAutoBackupEntry($meta),
                $manualKeep,
                !$isAuto ? $protectedPath : null,
                $changed
            );
        }

        if ($changed) {
            self::writeManifest($manifest);
        }
    }

    private static function pruneGroup(array $manifest, callable $filter, int $keep, ?string $protectedPath, bool $changed): array
    {
        $group = [];
        foreach ($manifest as $path => $meta) {
            if ($filter($meta)) {
                $group[$path] = $meta;
            }
        }

        if (count($group) <= $keep) {
            return [$manifest, $changed];
        }

        uasort($group, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $rightTime <=> $leftTime;
        });

        if ($protectedPath && isset($group[$protectedPath]) && array_key_first($group) !== $protectedPath) {
            $entry = $group[$protectedPath];
            unset($group[$protectedPath]);
            $group = [$protectedPath => $entry] + $group;
        }

        $index = 0;
        foreach ($group as $path => $meta) {
            $index++;
            if ($index <= $keep) {
                continue;
            }

            try {
                self::deleteBackupByPath($path);
            } catch (\Throwable $exception) {
                Log::warning('Failed deleting old backup during pruning', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }

            unset($manifest[$path]);
            $changed = true;
        }

        return [$manifest, $changed];
    }

    public static function isAutoBackupEntry(array $meta): bool
    {
        if (($meta['source'] ?? null) === self::AUTO_BACKUP_MARKER) {
            return true;
        }

        $createdBy = strtolower((string) ($meta['created_by_name'] ?? ''));
        if (in_array($createdBy, ['auto backup', 'system'], true)) {
            return true;
        }

        $description = strtolower((string) ($meta['description'] ?? ''));
        return str_contains($description, 'auto backup')
            || str_contains($description, 'automatic scheduled backup');
    }

    public static function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, 2) . ' ' . $units[$index];
    }

    public static function resolveNotificationRecipient(): ?string
    {
        $payload = self::readSettingsPayload(self::configString('tenant-backup.settings.general_type', 'general_settings'));
        $email = $payload[self::configString('tenant-backup.settings.email_key', 'default_email')] ?? null;
        $email = is_string($email) ? trim($email) : null;

        return ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
    }

    public static function resolveAccountName(): ?string
    {
        $payload = self::readSettingsPayload(self::configString('tenant-backup.settings.general_type', 'general_settings'));
        $name = $payload[self::configString('tenant-backup.settings.company_name_key', 'company_name')] ?? null;
        $name = is_string($name) ? trim($name) : null;

        return $name !== '' && $name !== null ? $name : config('app.name');
    }

    private static function runTenantScopedCommand(string $command, array $arguments, string $logLabel): void
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        if (!$tenant) {
            Log::warning('Skipping tenant-scoped backup step: no current tenant in context.', [
                'command' => $command,
            ]);
            return;
        }

        $tenantId = (string) $tenant->getTenantKey();
        $exitCode = Artisan::call($command, array_merge([
            '--tenants' => [$tenantId],
        ], $arguments));

        Log::info($logLabel, [
            'tenant' => $tenantId,
            'exit_code' => $exitCode,
            'output' => Artisan::output(),
            'command' => $command,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException($command . ' failed for tenant ' . $tenantId);
        }
    }

    private static function readSettingsPayload(string $type): array
    {
        try {
            $modelClass = self::settingsModelClass();
            if (!class_exists($modelClass)) {
                return [];
            }

            /** @var Model|null $row */
            $row = $modelClass::query()->where('type', $type)->first();
            if (!$row) {
                return [];
            }

            $column = self::configString('tenant-backup.settings.data_column', 'data');
            $raw = method_exists($row, 'getRawOriginal') ? $row->getRawOriginal($column) : $row->{$column};
            $decoded = is_string($raw) ? json_decode($raw, true) : (array) $raw;

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $exception) {
            Log::warning('Failed reading tenant-backup settings payload', [
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    private static function writeSettingsPayload(string $type, array $payload): void
    {
        $modelClass = self::settingsModelClass();
        if (!class_exists($modelClass)) {
            throw new \RuntimeException('Configured settings model class does not exist: ' . $modelClass);
        }

        $column = self::configString('tenant-backup.settings.data_column', 'data');
        $modelClass::query()->updateOrCreate(
            ['type' => $type],
            [$column => json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    }

    private static function settingsModelClass(): string
    {
        return self::configString('tenant-backup.models.settings', 'App\\Models\\Settings');
    }

    private static function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function deleteDirectoryRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = @scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && !is_link($full)) {
                self::deleteDirectoryRecursive($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
