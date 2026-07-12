# Getting started

A guided walkthrough: install the client, activate a license, verify it from code, gate routes,
and wire the CI gate.

## 1. Install and configure

```bash
composer require laranail/license-verifier
php artisan vendor:publish --tag=license-verifier-config
```

Pick the driver and credentials in `.env` (see [Installation](installation.md)). One config value
— `license-verifier.default` — switches the whole client between the 14 built-in providers
([Drivers](tools/drivers.md)).

## 2. Activate

From the CLI (or the [TUI dashboard](tools/tui.md), `php artisan license:manage`):

```bash
php artisan license:activate YOUR-LICENSE-KEY
```

Or from code:

```php
use Simtabi\Laranail\Licence\Verifier\Facades\LicenseVerifier;

LicenseVerifier::activate('YOUR-LICENSE-KEY');
```

Activation fingerprints the device, registers the seat with the provider, and persists the
result encrypted at rest (see [Security](security.md)).

## 3. Verify

```php
LicenseVerifier::isValid();          // offline-capable validity check
LicenseVerifier::getLicenseInfo();   // licensed-to, expiry, seats, entitlements

// Provider-agnostic, via the active driver:
app(\Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager::class)
    ->active()
    ->verify();                      // → VerificationResult
```

Verification is cached and grace-aware: a temporarily unreachable provider serves the last good
result within the grace window instead of taking your app down.

## 4. Gate routes

Attach the `license` middleware alias (configurable redirect/abort, excluded routes):

```php
Route::middleware('license')->group(function () {
    // licensed-only routes
});
```

Or auto-apply it to whole groups via `license-verifier.middleware_groups`.

## 5. Gate deploys

`license:status` returns CI-friendly exit codes (`0` valid / `1` invalid / `2` unreachable):

```bash
php artisan license:status --strict --json   # block a deploy when unlicensed
```

## Next steps

- [CLI reference](tools/cli.md) — all `laranail::license-verifier.*` / `license:*` commands.
- [TUI dashboard](tools/tui.md) — the interactive `license:manage` dashboard.
- [Drivers](tools/drivers.md) — the provider matrix, capabilities, and custom drivers.
- [Configuration](configuration.md) — source, storage, cache, heartbeat, bindings, security.
- [Architecture](architecture.md) — how the orchestrator, drivers, and stores fit together.

---

[← Docs index](../README.md#documentation)
