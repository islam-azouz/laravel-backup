@php
    $title = $moduleName ?? __('Database Backups');
    $storeRoute = route($routeNames['store'] ?? 'settings.backups.store');
    $autoSettingsRoute = route($routeNames['auto_settings_store'] ?? 'settings.backups.auto-settings.store');
    $restoreLockRoute = route($routeNames['restore_lock'] ?? 'settings.backups.restore-lock');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; color: #122033; }
        .page-shell { max-width: 1180px; margin: 0 auto; padding: 32px 16px 48px; }
        .hero { background: linear-gradient(135deg, #10243f 0%, #1e4e79 48%, #2fa36b 100%); color: #fff; border-radius: 24px; padding: 28px; box-shadow: 0 24px 60px rgba(16, 36, 63, .18); }
        .panel { background: #fff; border: 1px solid #e7ecf3; border-radius: 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, .05); }
        .table td, .table th { vertical-align: middle; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-uppercase small opacity-75 mb-2">{{ __('Tenant Backup Package') }}</div>
                <h1 class="h3 mb-2">{{ $title }}</h1>
                <p class="mb-0 opacity-75">{{ __('Create, restore, and manage tenant database snapshots from one place.') }}</p>
            </div>
            <div class="text-end">
                <div class="small opacity-75">{{ __('Current backups') }}</div>
                <div class="display-6 fw-semibold">{{ count($items) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="panel p-4 h-100">
                <h2 class="h5 mb-3">{{ __('Create backup') }}</h2>
                <form id="backup-create-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea class="form-control" name="description" rows="3" maxlength="1000" placeholder="{{ __('Optional notes about this backup') }}"></textarea>
                    </div>
                    <button class="btn btn-success" type="submit">{{ __('Create Backup') }}</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel p-4 h-100">
                <h2 class="h5 mb-3">{{ __('Auto backup settings') }}</h2>
                <form id="auto-settings-form">
                    @csrf
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" {{ !empty($autoSettings['enabled']) ? 'checked' : '' }}>
                        <label class="form-check-label" for="enabled">{{ __('Enable automatic backups') }}</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Every hours') }}</label>
                            <input class="form-control" type="number" min="1" max="24" name="frequency_hours" value="{{ $autoSettings['frequency_hours'] ?? 24 }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('From') }}</label>
                            <input class="form-control" type="number" min="0" max="23" name="start_hour" value="{{ $autoSettings['start_hour'] ?? 0 }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('To') }}</label>
                            <input class="form-control" type="number" min="0" max="23" name="end_hour" value="{{ $autoSettings['end_hour'] ?? 23 }}">
                        </div>
                    </div>
                    <div class="small text-muted mt-3">{{ __('Last run') }}: {{ $autoSettings['last_run_at'] ?? __('Never') }}</div>
                    <button class="btn btn-outline-primary mt-3" type="submit">{{ __('Save Settings') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="panel p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
            <h2 class="h5 mb-0">{{ __('Available backups') }}</h2>
            <span class="text-muted small">{{ __('Newest backups appear first after refresh.') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('File') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Size') }}</th>
                        <th>{{ __('Created By') }}</th>
                        <th>{{ __('Created At') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $item['filename'] }}</div>
                            <div class="mono text-muted">{{ $item['path'] }}</div>
                        </td>
                        <td>{{ $item['description'] ?: '-' }}</td>
                        <td>{{ $item['size_human'] }}</td>
                        <td>{{ $item['created_by_name'] }}</td>
                        <td>{{ $item['created_at_human'] ?: '-' }}</td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-sm btn-warning" type="button" onclick="restoreBackup('{{ $item['id'] }}')">{{ __('Restore') }}</button>
                                <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteBackup('{{ $item['id'] }}')">{{ __('Delete') }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">{{ __('No backups found yet.') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const backupRoutes = {
        store: @json($storeRoute),
        autoSettings: @json($autoSettingsRoute),
        destroyBase: @json(url($destroyUrl)),
        restoreBase: @json(url($restoreUrl)),
        restoreLock: @json($restoreLockRoute),
    };

    async function postForm(url, formData, method = 'POST') {
        const response = await fetch(url, {
            method,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: formData,
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.message || response.statusText);
        }

        return payload;
    }

    document.getElementById('backup-create-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const payload = await postForm(backupRoutes.store, new FormData(event.currentTarget));
            alert(payload.message || 'Backup created successfully');
            window.location.reload();
        } catch (error) {
            alert(error.message);
        }
    });

    document.getElementById('auto-settings-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(event.currentTarget);
        if (!formData.has('enabled')) {
            formData.append('enabled', '0');
        }
        try {
            const payload = await postForm(backupRoutes.autoSettings, formData);
            alert(payload.message || 'Settings saved successfully');
            window.location.reload();
        } catch (error) {
            alert(error.message);
        }
    });

    async function deleteBackup(id) {
        if (!confirm(@json(__('Delete this backup permanently?')))) {
            return;
        }

        const formData = new FormData();
        formData.append('_method', 'DELETE');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        try {
            const payload = await postForm(`${backupRoutes.destroyBase}/${encodeURIComponent(id)}`, formData);
            alert(payload.message || 'Backup deleted successfully');
            window.location.reload();
        } catch (error) {
            alert(error.message);
        }
    }

    async function restoreBackup(id) {
        if (!confirm(@json(__('Restoring will overwrite the current tenant database. Continue?')))) {
            return;
        }

        const backupBefore = confirm(@json(__('Create a safety backup before restore?')));
        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        formData.append('backup_before', backupBefore ? '1' : '0');

        try {
            await postForm(`${backupRoutes.restoreBase}/${encodeURIComponent(id)}`, formData);
            window.location.href = backupRoutes.restoreLock;
        } catch (error) {
            alert(error.message);
        }
    }

    window.deleteBackup = deleteBackup;
    window.restoreBackup = restoreBackup;
</script>
</body>
</html>
