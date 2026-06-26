# Upgrade guide

## → Unreleased (laranail hard fork)

This release re-homes the package under the laranail org and makes it a headless,
provider-agnostic verification core. The changes below are breaking.

### 1. Package tooling
`spatie/laravel-package-tools` is replaced by `laranail/package-tools`. If you only
consume the package (not extend its provider) no action is required — discovery is
automatic.

### 2. Config file rename
The published config is now `config/license-verifier.php` (was `licensing-client.php`).

```bash
# remove the old published file and re-publish
rm config/licensing-client.php
php artisan vendor:publish --tag=license-verifier-config
```

All `config('licensing-client.*')` calls become `config('license-verifier.*')`.

### 3. Environment variables
Variables are renamed from `LICENSING_*` to `LICENSE_VERIFIER_*`:

| Old | New |
|-----|-----|
| `LICENSING_SERVER_URL` | `LICENSE_VERIFIER_SERVER_URL` |
| `LICENSING_KEY` | `LICENSE_VERIFIER_KEY` |
| `LICENSING_PUBLIC_KEY` | `LICENSE_VERIFIER_PUBLIC_KEY` |
| `LICENSING_ISSUER` | `LICENSE_VERIFIER_ISSUER` |
| `LICENSING_*` (all others) | `LICENSE_VERIFIER_*` |

The old `LICENSING_*` names are still read as a fallback for one minor release, then
removed. Migrate your `.env` at your convenience.

### 4. Class/alias renames
- Facade alias: `LicenceVerifier` → **`LicenseVerifier`**.
- Facade class: `…\Facades\LicenceVerifierFacade` → **`…\Facades\LicenceVerifier`**.
- Service provider: `…\LicenceVerifierServiceProvider` → **`…\Providers\LicenceVerifierServiceProvider`**.

### 5. Database table
The cache/record table is renamed `licensing_client_cache` → **`license_verifier_licenses`**
with added columns (`driver`, `domain`, `licensed_to`, `status`). Re-publish and run the
migration if you use the database store:

```bash
php artisan vendor:publish --tag=license-verifier-migrations
php artisan migrate
```

### 6. PHP / dependencies
- PHP floor is now `^8.4 || ^8.5`.
- `paragonie/paseto` is now `^3.5`.
