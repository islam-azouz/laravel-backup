# tenancy-tools/laravel-tenant-backup

Tenant-aware Laravel backup package built on top of `spatie/laravel-backup`.

## Supported Versions

- Laravel 9, 10, 11, and 12
- PHP 8.0+

The package Composer constraints are intentionally broad enough to install into older Laravel apps, while Laravel itself still decides the final PHP version required in each target app.

## Features

- create, list, delete, and restore database backups
- restore lock screen and restore-state middleware
- per-tenant backup metadata manifest
- automatic backup schedule settings stored in the tenant settings model
- customized Spatie backup notification fields
- built-in routes, views, policy, and restore-state migration

## Assumptions

This package is designed for apps that:

- use Laravel 9+
- use `spatie/laravel-backup`
- run in a tenant context where `tenant()` is available
- store settings in a model similar to `App\\Models\\Settings`
- restore MySQL dumps through the local `mysql` CLI binary

## Install From GitHub

Inside the target application:

```bash
composer config repositories.tenant-backup vcs https://github.com/islam-azouz/laravel-backup.git
composer require tenancy-tools/laravel-tenant-backup:dev-main
php artisan vendor:publish --tag=tenant-backup-config
php artisan vendor:publish --tag=tenant-backup-migrations
php artisan migrate
```

You still need the `composer config repositories...` step even when the repository is public, because this package is hosted on GitHub directly and is not published on Packagist.

If you later publish the package on Packagist, installation becomes simply:

```bash
composer require tenancy-tools/laravel-tenant-backup:dev-main
```

If the GitHub repository is private, add a GitHub token first:

```bash
composer config repositories.tenant-backup vcs https://github.com/islam-azouz/laravel-backup.git
composer config --global github-oauth.github.com YOUR_GITHUB_TOKEN
composer require tenancy-tools/laravel-tenant-backup:dev-main
```

## Post Install

Review `config/tenant-backup.php` and adjust only what is app-specific:

- `models.settings`
- `routes.middleware`
- `restore.central_connection`
- `artisanal.*`
- `permissions.*`

Also ensure `config/backup.php` keeps using your desired notification classes. The package can work with its own notification classes or your app-level wrappers.

## Publish This Package From The Monorepo

From the source project:

```bash
git subtree split --prefix=packages/tenancy-tools/laravel-tenant-backup -b tenant-backup-package
git remote add tenant-backup git@github.com:YOUR-USERNAME/laravel-tenant-backup.git
git push tenant-backup tenant-backup-package:main
```

## Notes

- the package ships its own routes and UI
- the package ships a migration for the `restore_state` column on `tenants`
- if your target app does not use Stancl Tenancy, set `routes.middleware` to the middleware stack you need
