# laranail/license-verifier

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/license-verifier.svg)](https://packagist.org/packages/laranail/license-verifier)
[![Tests](https://github.com/laranail/license-verifier/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/license-verifier/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/license-verifier/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/license-verifier/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Headless, provider-agnostic license verification for Laravel — PASETO/Ed25519 offline verification, device fingerprinting, seats, grace periods, and pluggable drivers for 12 licensing providers. CLI/TUI-first; the web UI ships as separate presets via [`laranail/license-verifier-ui`](https://opensource.simtabi.com/documentation/laranail/license-verifier-ui/).

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13`.

## Install

```bash
composer require laranail/license-verifier
php artisan vendor:publish --tag=license-verifier-config
```

See [Installation](docs/installation.md) for the migration publish and the guided installer.

## Quick start

Set the active driver and credentials in `.env` (prefix `LICENSE_VERIFIER_*`):

```dotenv
LICENSE_VERIFIER_DRIVER=paseto
LICENSE_VERIFIER_SERVER_URL=https://licensing.example.com
LICENSE_VERIFIER_PUBLIC_KEY=...
LICENSE_VERIFIER_KEY=YOUR-LICENSE-KEY
```

Activate and verify — the same API for every provider:

```php
use Simtabi\Laranail\Licence\Verifier\Facades\LicenseVerifier;

LicenseVerifier::activate('YOUR-LICENSE-KEY');
LicenseVerifier::isValid();          // offline-capable check
LicenseVerifier::getLicenseInfo();
```

Gate routes with the `license` middleware, or gate deploys from the CLI:

```bash
php artisan license:manage          # interactive TUI dashboard
php artisan license:status --strict --json   # CI gate (exit 0 valid / 1 invalid / 2 unreachable)
```

Full tour: [Getting started](docs/getting-started.md).

## Drivers

One config value (`license-verifier.default`) switches the source: `paseto` (self-hosted
[`laranail/license-kit`](https://opensource.simtabi.com/documentation/laranail/license-kit/), default) ·
`envato` · `keygen` · `lemonsqueezy` · `gumroad` · `cryptolens` · `licensespring` · `freemius` ·
`edd` · `woocommerce` · `paddle` · `unlocksh` · `generic` (config-mapped escape hatch) · `null` (dev).
Drivers declare capabilities (offline tokens, refresh, heartbeat, entitlements, seats, domain
binding), and you can register your own via `DriverManager::extend()` — see
[Drivers](docs/tools/drivers.md).

## <a name="documentation"></a>Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/license-verifier](https://opensource.simtabi.com/documentation/laranail/license-verifier/)**.

### Guides

- [Installation](docs/installation.md) — requirements, publishable assets, first configuration.
- [Getting started](docs/getting-started.md) — activate, verify, gate routes and deploys.
- [Configuration](docs/configuration.md) — every config key, env var, and runtime overrides.
- [Architecture](docs/architecture.md) — the orchestrator, driver layer, and lifecycle diagrams.
- [Security](docs/security.md) — encryption pipeline, tiered storage, offline trust model, threat checklist.
- [Release](docs/release.md) — tag-driven releases and versioning policy.

### Reference

- [Drivers](docs/tools/drivers.md) — the 14 drivers, capability matrix, generic and custom drivers.
- [CLI](docs/tools/cli.md) — all `laranail::license-verifier.*` / `license:*` commands and exit codes.
- [TUI dashboard](docs/tools/tui.md) — the interactive `license:manage` dashboard.

### Project

- [Changelog](CHANGELOG.md) — release history.
- [Upgrade guide](UPGRADE.md) — migrating from the pre-fork releases.
- [Audit & feature-tracking matrix](docs/audit.md) — the refactor/remediation ledger.

## Stability

Pre-1.0 (`0.x`) — the public API may change between minor versions. Pin a version before bumping.

## Local development

```bash
composer test     # Pest (Unit + Feature)
composer lint     # pint --test + phpstan + rector --dry-run
```

## Sister packages

- [`laranail/license-verifier-ui`](https://opensource.simtabi.com/documentation/laranail/license-verifier-ui/) — Blade / Livewire / Filament / Vue UI presets for this client.
- [`laranail/license-kit`](https://opensource.simtabi.com/documentation/laranail/license-kit/) — the self-hosted PASETO/Ed25519 licensing server (issuer + seat registry).
- [`laranail/product-updater`](https://opensource.simtabi.com/documentation/laranail/product-updater/) — license-gated product updates, built on this client.
- [`laranail/demo-mode`](https://opensource.simtabi.com/documentation/laranail/demo-mode/) — demo/sandbox restrictions for licensed products.

## Community

- [Issues](https://github.com/laranail/license-verifier/issues) — bugs and feature requests.

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
