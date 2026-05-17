<?php

namespace TenancyTools\TenantBackup\Http\Controllers;

use TenancyTools\TenantBackup\Support\BackupHelper;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BackupSettingsController extends Controller
{
    use AuthorizesRequests;

    private function authorizationModelClass(): string
    {
        return (string) config('tenant-backup.models.authorization', 'TenancyTools\\TenantBackup\\Models\\BackupFile');
    }

    private function restoreCooldownSeconds(): int
    {
        return (int) config('tenant-backup.restore.cooldown_seconds', 1);
    }

    private function restoreLockGraceSeconds(): int
    {
        return (int) config('tenant-backup.restore.lock_grace_seconds', 1);
    }

    private function pageData(): array
    {
        return [
            'items' => $this->collectBackupItems(),
            'autoSettings' => BackupHelper::getAutoSettings(),
            'backupModel' => $this->authorizationModelClass(),
            'routeNames' => config('tenant-backup.ui.route_names', []),
            'destroyUrl' => config('tenant-backup.ui.destroy_url', 'settings/backups'),
            'restoreUrl' => config('tenant-backup.ui.restore_url', 'settings/backups/restore'),
            'moduleName' => __('Database Backups'),
        ];
    }

    public function index()
    {
        $this->authorize('read', $this->authorizationModelClass());

        return view(config('tenant-backup.ui.index_view', 'tenant-backup::index'), $this->pageData());
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('read', $this->authorizationModelClass());

        $items = $this->collectBackupItems();
        $globalRaw = $request->input('filters');
        $search = '';
        if (is_string($globalRaw) && $globalRaw !== '') {
            $decoded = json_decode($globalRaw, true);
            if (is_array($decoded) && isset($decoded['global']['value'])) {
                $search = (string) $decoded['global']['value'];
            }
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
                foreach (['filename', 'description', 'created_by_name', 'created_at'] as $field) {
                    if (str_contains(mb_strtolower((string) ($item[$field] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $sortField = (string) $request->input('sortField', 'created_at');
        $sortOrder = (int) $request->input('sortOrder', -1) >= 0 ? 1 : -1;
        usort($items, static function (array $left, array $right) use ($sortField, $sortOrder): int {
            $leftValue = $left[$sortField] ?? null;
            $rightValue = $right[$sortField] ?? null;

            return $sortOrder * (is_numeric($leftValue) && is_numeric($rightValue)
                ? ($leftValue <=> $rightValue)
                : strcmp((string) $leftValue, (string) $rightValue));
        });

        $total = count($items);
        $first = max(0, (int) $request->input('first', 0));
        $rows = max(1, (int) $request->input('rows', 25));

        return response()->json([
            'data' => array_slice($items, $first, $rows),
            'totalRecords' => $total,
            'recordsFiltered' => $total,
        ]);
    }

    protected function collectBackupItems(): array
    {
        $manifest = BackupHelper::readManifest();
        $items = [];

        foreach (BackupHelper::destinations() as $destination) {
            $diskName = $destination->diskName();
            foreach ($destination->backups() as $backup) {
                $path = $backup->path();
                $meta = $manifest[$path] ?? [];
                $size = (int) $backup->sizeInBytes();

                $items[] = [
                    'id' => BackupHelper::encodeId($diskName, $path),
                    'disk' => $diskName,
                    'path' => $path,
                    'filename' => basename($path),
                    'description' => $meta['description'] ?? null,
                    'size' => $size,
                    'size_human' => BackupHelper::humanFileSize($size),
                    'created_at' => optional($backup->date())->toDateTimeString(),
                    'created_at_human' => optional($backup->date())->toDateTimeString(),
                    'created_by_id' => $meta['created_by_id'] ?? null,
                    'created_by_name' => $meta['created_by_name'] ?? '-',
                ];
            }
        }

        return $items;
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', $this->authorizationModelClass());

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = Auth::user();

        try {
            $this->createBackupSnapshot([
                'description' => $validated['description'] ?? null,
                'created_by_id' => $user?->id,
                'created_by_name' => $user?->name ?? '-',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Backup failed', ['error' => $exception->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('Failed to create backup') . ': ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Backup created successfully'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete', $this->authorizationModelClass());

        [$diskName, $path] = BackupHelper::parseId($id);
        $backup = BackupHelper::findBackup($diskName, $path);
        if (!$backup) {
            return response()->json([
                'success' => false,
                'message' => __('Backup not found'),
            ], 404);
        }

        $backup->delete();
        $manifest = BackupHelper::readManifest();
        if (isset($manifest[$path])) {
            unset($manifest[$path]);
            BackupHelper::writeManifest($manifest);
        }

        return response()->json([
            'success' => true,
            'message' => __('Backup deleted successfully'),
        ]);
    }

    public function restore(string $id, Request $request): JsonResponse
    {
        $this->authorize('restore', $this->authorizationModelClass());

        $request->validate([
            'backup_before' => ['nullable', 'boolean'],
        ]);

        [$diskName, $path] = BackupHelper::parseId($id);
        if (!BackupHelper::findBackup($diskName, $path)) {
            return response()->json([
                'success' => false,
                'message' => __('Backup not found'),
            ], 404);
        }

        BackupHelper::startRestoreMode();

        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        ignore_user_abort(true);
        @set_time_limit(0);

        sleep($this->restoreLockGraceSeconds());

        $this->runRestore(
            $diskName,
            $path,
            $request->boolean('backup_before', true),
            Auth::id(),
            Auth::user()?->name ?? '-',
        );

        return response()->json(['success' => true]);
    }

    private function runRestore(string $diskName, string $path, bool $backupBefore, ?int $userId, string $userName): void
    {
        $sqlPath = null;
        $succeeded = false;

        try {
            if ($backupBefore) {
                $this->safePreRestoreBackup($userId, $userName);
            }

            $sqlPath = BackupHelper::extractSqlFromBackup($diskName, $path);
            BackupHelper::runSqlFile($sqlPath);
            BackupHelper::runTenantMigrationsForCurrentTenant();
            BackupHelper::runTenantDataUpdatesForCurrentTenant();
            BackupHelper::runUpdateDataSeederForCurrentTenant();
            $succeeded = true;
        } catch (\Throwable $exception) {
            Log::error('Restore failed', ['error' => $exception->getMessage()]);
        } finally {
            if ($sqlPath && is_file($sqlPath) && !@unlink($sqlPath)) {
                Log::warning('Failed to remove temporary restore file', ['path' => $sqlPath]);
            }

            if ($succeeded) {
                sleep($this->restoreCooldownSeconds());
                BackupHelper::endRestoreMode();
            }
        }
    }

    private function safePreRestoreBackup(?int $userId, string $userName): void
    {
        try {
            $this->createBackupSnapshot([
                'description' => '[' . __('Pre-restore safety backup') . '] ' . __('Automatic snapshot taken before restoring a backup'),
                'created_by_id' => $userId,
                'created_by_name' => $userName,
                'source' => 'pre_restore',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Pre-restore backup failed, continuing anyway', ['error' => $exception->getMessage()]);
        }
    }

    public function restoreStatus(): JsonResponse
    {
        return response()->json([
            'locked' => BackupHelper::isInRestoreMode(),
        ]);
    }

    public function restoreLockView()
    {
        return response()->view(config('tenant-backup.restore.lock_view', 'tenant-backup::restore-lock'));
    }

    private function createBackupSnapshot(array $meta, bool $isAuto = false): ?string
    {
        BackupHelper::configureSpatieForCurrentConnection();
        $existingPaths = BackupHelper::collectAllBackupPaths();

        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()));
        }

        $newPaths = array_values(array_diff(BackupHelper::collectAllBackupPaths(), $existingPaths));
        $createdPath = end($newPaths) ?: null;

        if ($createdPath) {
            $manifest = BackupHelper::readManifest();
            $manifest[$createdPath] = array_merge(['created_at' => now()->toDateTimeString()], $meta);
            BackupHelper::writeManifest($manifest);
            BackupHelper::pruneBackupsPerConfig($createdPath, $isAuto);
        }

        return $createdPath;
    }

    public function getAutoSettings(): JsonResponse
    {
        $this->authorize('read', $this->authorizationModelClass());

        return response()->json([
            'success' => true,
            'data' => BackupHelper::getAutoSettings(),
        ]);
    }

    public function saveAutoSettings(Request $request): JsonResponse
    {
        $this->authorize('create', $this->authorizationModelClass());

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'end_hour' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        $saved = BackupHelper::saveAutoSettings([
            'enabled' => (bool) $validated['enabled'],
            'frequency_hours' => (int) $validated['frequency_hours'],
            'start_hour' => (int) $validated['start_hour'],
            'end_hour' => (int) $validated['end_hour'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Settings saved successfully'),
            'data' => $saved,
        ]);
    }
}
