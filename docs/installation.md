# Installation

Requirements, the Composer install, and the publishable assets.

```bash
composer require laranail/license-verifier
```

The `LicenceVerifierServiceProvider` (built on `laranail/package-tools`) and the
`LicenseVerifier` facade alias are auto-discovered. Publish the config to customise it:

```bash
php artisan vendor:publish --tag=license-verifier-config
```

If you use the `database` storage backend or the `model` license-detail source, publish and run
the migration too:

```bash
php artisan vendor:publish --tag=license-verifier-migrations
php artisan migrate
```

Or run the guided installer, which publishes the config and offers the migration in one step:

```bash
php artisan laranail::license-verifier.install
```

## Requirements

- PHP `^8.4.1 || ^8.5` with the `sodium`, `json`, and `openssl` extensions
- Laravel `^13`

## First configuration

Set the active driver and its credentials in `.env` (prefix `LICENSE_VERIFIER_*`; the legacy
`LICENSING_*` names are still read as a fallback):

```dotenv
LICENSE_VERIFIER_DRIVER=paseto
LICENSE_VERIFIER_SERVER_URL=https://licensing.example.com
LICENSE_VERIFIER_PUBLIC_KEY=...
LICENSE_VERIFIER_KEY=YOUR-LICENSE-KEY
```

Every key is described in [Configuration](configuration.md); the per-provider blocks are in
[Drivers](tools/drivers.md). Verify the setup with `php artisan license:doctor`.

## Upgrading

Migrating from a pre-fork release (`licensing-client` config, `LICENSING_*` env vars)? Follow
[UPGRADE.md](../UPGRADE.md).

---

[← Docs index](../README.md#documentation)
