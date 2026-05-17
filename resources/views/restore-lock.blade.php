@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
    $bootstrapHref = $rtl
        ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css'
        : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
    $logoPath = config('tenant-backup.ui.logo_path', 'assets/media/logos/anevex-logo.png');
    $appLogo = function_exists('global_asset') ? global_asset($logoPath) : asset($logoPath);
    $appName = config('app.name', 'App');
    $routeNames = config('tenant-backup.ui.route_names', []);
    $dashboardRoute = config('tenant-backup.ui.dashboard_route', 'dashboard');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ __('Account restore in progress') }} - {{ $appName }}</title>

    <link rel="shortcut icon" href="{{ $appLogo }}" />
    <link rel="icon" type="image/png" href="{{ $appLogo }}" />
    <link rel="apple-touch-icon" href="{{ $appLogo }}" />
    <link href="{{ $bootstrapHref }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; font-family: "Tahoma", "Arial", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(36,201,157,.13) 0 0, transparent 180px),
                radial-gradient(circle at bottom left, rgba(36,201,157,.08) 0 0, transparent 180px),
                radial-gradient(circle at right center, rgba(36,201,157,.07) 0 0, transparent 220px),
                #ffffff;
            display: flex; align-items: center; justify-content: center; overflow: hidden; color: #fff; position: relative;
        }
        .dots { position: fixed; top: 96px; left: 32px; width: 95px; height: 70px; background-image: radial-gradient(#2fcfa3 2px, transparent 2px); background-size: 18px 18px; opacity: .7; z-index: 1; }
        .circle-line { position: fixed; border: 1px solid rgba(36,201,157,.16); border-radius: 50%; z-index: 0; }
        .circle-left-top { width: 310px; height: 310px; top: -140px; left: -160px; }
        .circle-left-bottom { width: 300px; height: 300px; bottom: -185px; left: -145px; }
        .circle-right { width: 270px; height: 270px; right: -105px; top: 215px; box-shadow: 0 0 0 12px rgba(36,201,157,.035), 0 0 0 24px rgba(36,201,157,.03), 0 0 0 36px rgba(36,201,157,.025), 0 0 0 48px rgba(36,201,157,.02), 0 0 0 60px rgba(36,201,157,.016), 0 0 0 72px rgba(36,201,157,.012); }
        .restore-card { width: min(700px, calc(100% - 32px)); border-radius: 12px; overflow: hidden; position: relative; z-index: 5; box-shadow: 0 24px 55px rgba(21,31,53,.22); }
        .restore-card::before { content: ""; position: absolute; top: 0; right: 0; left: 0; height: 4px; background: #28d39f; z-index: 3; }
        .main-panel { position: relative; min-height: 555px; padding: 44px 70px 28px; text-align: center; background: radial-gradient(circle at 50% 38%, rgba(255,255,255,.06), transparent 210px), radial-gradient(circle at 25% 35%, rgba(255,255,255,.04), transparent 90px), linear-gradient(135deg, #20243a 0%, #252a42 45%, #1f2336 100%); }
        .logo-wrap { position: relative; width: 150px; height: 150px; margin: 14px auto 26px; display: flex; align-items: center; justify-content: center; }
        .logo-ring { position: absolute; inset: 0; border: 1px solid rgba(36,201,157,.13); border-radius: 50%; }
        .logo-ring::before { content: ""; position: absolute; inset: 13px; border: 1px solid rgba(36,201,157,.12); border-radius: 50%; }
        .logo-dot { position: absolute; width: 9px; height: 9px; border-radius: 50%; background: #28d39f; left: 18px; top: 39px; box-shadow: 0 0 15px rgba(40,211,159,.7); }
        .logo-circle { width: 116px; height: 116px; border-radius: 50%; background: linear-gradient(145deg, #2bdba6, #20b987); display: flex; align-items: center; justify-content: center; box-shadow: inset 0 -12px 22px rgba(0,0,0,.12), 0 18px 40px rgba(32,185,135,.22); position: relative; z-index: 2; }
        .logo-circle img { width: 78%; height: 78%; object-fit: contain; filter: drop-shadow(0 4px 10px rgba(0,0,0,.18)); }
        .title { font-size: 32px; font-weight: 800; margin-bottom: 13px; color: #fff; text-shadow: 0 2px 5px rgba(0,0,0,.18); }
        .small-line { width: 40px; height: 2px; background: #28d39f; margin: 0 auto 18px; opacity: .9; }
        .desc { color: rgba(255,255,255,.9); font-size: 16px; line-height: 1.9; margin-bottom: 18px; }
        .status-box { width: 480px; max-width: 100%; margin: 0 auto 17px; padding: 18px 24px; border-radius: 14px; background: rgba(255,255,255,.055); border: 1px solid rgba(255,255,255,.075); box-shadow: inset 0 1px 0 rgba(255,255,255,.08), 0 12px 25px rgba(0,0,0,.13); backdrop-filter: blur(6px); display: grid; grid-template-columns: 1fr 1px 1fr; align-items: center; gap: 22px; }
        .divider { width: 1px; height: 54px; background: rgba(255,255,255,.15); }
        .status-item .icon { width: 28px; height: 28px; margin: 0 auto 8px; border-radius: 50%; background: #28d39f; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px; box-shadow: 0 0 0 4px rgba(40,211,159,.13); }
        .status-item .label { font-size: 13px; color: rgba(255,255,255,.78); margin-bottom: 6px; }
        .status-item .value { color: #28d39f; font-size: 15px; font-weight: 800; }
        .checking { font-size: 13px; font-weight: 700; color: rgba(255,255,255,.9); margin-bottom: 8px; }
        .progress-track { width: 410px; max-width: 100%; height: 15px; border-radius: 999px; background: rgba(255,255,255,.22); overflow: hidden; margin: 0 auto 16px; box-shadow: inset 0 2px 5px rgba(0,0,0,.25); }
        .progress-fill { height: 100%; width: 40%; border-radius: 999px; background: repeating-linear-gradient(-45deg, rgba(255,255,255,.08) 0 9px, rgba(255,255,255,0) 9px 18px), linear-gradient(90deg, #25c995, #2bdba6); box-shadow: 0 0 14px rgba(40,211,159,.35); animation: indeterminate 1.6s ease-in-out infinite, move 1.2s linear infinite; }
        @keyframes move { from { background-position: 0 0; } to { background-position: 36px 0; } }
        @keyframes indeterminate { 0% { margin-inline-start: -40%; width: 40%; } 50% { margin-inline-start: 30%; width: 50%; } 100% { margin-inline-start: 100%; width: 40%; } }
        .auto-text { color: rgba(255,255,255,.66); font-size: 12px; }
        .bottom-note { background: linear-gradient(90deg, #eefdfb, #f9ffff); color: #2f3b4c; padding: 12px 36px; min-height: 64px; display: flex; align-items: center; justify-content: center; gap: 14px; font-size: 13px; box-shadow: inset 0 1px 0 rgba(255,255,255,.9); }
        .shield { width: 39px; height: 39px; border-radius: 10px; background: #fff; color: #28d39f; display: flex; align-items: center; justify-content: center; font-size: 23px; box-shadow: 0 8px 20px rgba(40,211,159,.13); flex: 0 0 auto; }
        @media (max-width: 576px) { .main-panel { padding: 34px 22px 24px; min-height: auto; } .title { font-size: 26px; } .desc { font-size: 14px; } .status-box { grid-template-columns: 1fr; gap: 16px; } .divider { display: none; } .bottom-note { padding: 14px 18px; font-size: 12px; } }
    </style>
</head>
<body>
    <div class="dots"></div>
    <div class="circle-line circle-left-top"></div>
    <div class="circle-line circle-left-bottom"></div>
    <div class="circle-line circle-right"></div>

    <div class="restore-card">
        <div class="main-panel">
            <div class="logo-wrap">
                <div class="logo-ring"></div>
                <div class="logo-dot"></div>
                <div class="logo-circle"><img src="{{ $appLogo }}" alt="{{ $appName }}" /></div>
            </div>

            <h1 class="title">{{ __('Account restore in progress') }}</h1>
            <div class="small-line"></div>
            <div class="desc">{{ __('This account is currently being restored.') }}<br>{{ __('Please wait, you will be redirected automatically once the operation is complete.') }}</div>

            <div class="status-box">
                <div class="status-item">
                    <div class="icon"><i class="bi bi-arrow-clockwise"></i></div>
                    <div class="label">{{ __('Account status') }}</div>
                    <div class="value">{{ __('Restoring') }}</div>
                </div>
                <div class="divider"></div>
                <div class="status-item">
                    <div class="icon"><i class="bi bi-clock-fill"></i></div>
                    <div class="label">{{ __('Last status check') }}</div>
                    <div class="value" id="lastCheck">--:--:--</div>
                </div>
            </div>

            <div class="checking" id="checkingText">{{ __('Checking restore status...') }}</div>
            <div class="progress-track"><div class="progress-fill"></div></div>
            <div class="auto-text">{{ __('The system checks the restore status automatically every few seconds') }}</div>
        </div>

        <div class="bottom-note">
            <div class="shield"><i class="bi bi-shield-check"></i></div>
            <div>{{ __('Do not close this page, you will be redirected automatically once the restore completes.') }}</div>
        </div>
    </div>

    <script>
    (function () {
        const STATUS_URL = @json(route($routeNames['restore_status'] ?? 'settings.backups.restore-status'));
        const FALLBACK_URL = @json(route($dashboardRoute));
        const STORAGE_KEY = 'backup_restore_return_url';
        const POLL_MS = 5000;
        const lastCheckEl = document.getElementById('lastCheck');

        function resolveReturnUrl() {
            let raw;
            try { raw = sessionStorage.getItem(STORAGE_KEY); } catch (_) { raw = null; }
            if (!raw) return FALLBACK_URL;

            try {
                const url = new URL(raw, window.location.origin);
                if (url.origin !== window.location.origin) return FALLBACK_URL;
                if (url.pathname.includes('/backups/restore-lock')) return FALLBACK_URL;
                return url.pathname + url.search + url.hash;
            } catch (_) {
                return FALLBACK_URL;
            }
        }

        const RETURN_URL = resolveReturnUrl();

        function stamp() {
            lastCheckEl.textContent = new Date().toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }

        function poll() {
            stamp();
            fetch(STATUS_URL, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(json => {
                if (json && json.locked === false) {
                    try { sessionStorage.removeItem(STORAGE_KEY); } catch (_) {}
                    window.location.replace(RETURN_URL);
                    return;
                }
                setTimeout(poll, POLL_MS);
            })
            .catch(() => setTimeout(poll, POLL_MS));
        }

        stamp();
        setTimeout(poll, 800);
    })();
    </script>
</body>
</html>
