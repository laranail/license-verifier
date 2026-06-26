# CLAUDE.md

Guidance for Claude Code when working in `laranail/license-verifier`.

## What this package is

A **headless, provider-agnostic Laravel license verification client**. It activates and
verifies a product's license against a configurable source (default: the `laranail/license-kit`
PASETO/Ed25519 server) and supports many providers via a driver layer. It is **CLI/TUI-first** —
the web UI ships in a unified preset package (`laranail/license-verifier-ui`) that bundles the
Blade/Filament/Livewire/Vue presets and lets you enable the one(s) you need.
A companion `laranail/product-updater` consumes this package to gate updates.

- Namespace: `Simtabi\Laranail\Licence\Verifier\` (note the British "Licence" in the namespace;
  the composer slug and config key use the American "license-verifier").
- Config key / file: `license-verifier`. Env prefix: `LICENSE_VERIFIER_*` (legacy `LICENSING_*` fallback).
- Provider: `…\Providers\LicenceVerifierServiceProvider` (extends `laranail/package-tools`).
- Facade: `…\Facades\LicenceVerifier` (alias `LicenseVerifier`). Orchestrator: `…\LicenceVerifier`.

## Architecture

- **Drivers** (`src/Drivers/`) behind `Contracts/Driver.php` + capability sub-interfaces; resolved by
  `DriverManager` from `config('license-verifier.default')`. Default `PasetoDriver` wraps the
  `Services/{FingerprintGenerator,LicensingApiClient,TokenStorage,TokenValidator}`.
- **Stores** (`src/Stores/`) behind `Contracts/LicenseStore.php` (file/database/cache/callback).
- **Source/model**: configurable license-detail resolution; swappable `Models/LicenseRecord` via
  `config('license-verifier.models.license')`.
- **CLI/TUI**: commands under `src/Commands/*` use the `laranail/console` services and are namespaced
  `laranail::license-verifier.*` (with `license:*` aliases).

## Conventions

- Follow the laranail org conventions (`/opensource/laranail/CLAUDE.md`) and Simtabi conventions
  (`/opensource/CLAUDE.md`). This is a **hard fork** — do not re-merge the original upstream project.
- `declare(strict_types=1);` in every file; `final` classes where applicable; `#[Override]` on inherited methods.
- Explicit return types and param type hints. Curly braces on all control structures. Early returns. DRY.
- Prefer PHPDoc over inline comments; add array-shape PHPDoc where useful.
- Check sibling files / `laranail/package-tools` + `laranail/console` APIs before adding new patterns.

## Tracking

`docs/AUDIT.md` is the master feature/bug/convention tracking matrix (stable IDs). Update item
statuses as work lands; it is the gate for "is everything implemented".

## Commands

```bash
composer test           # Pest (Unit + Feature)
composer test-coverage  # with coverage
composer lint           # pint --test + phpstan + rector --dry-run
composer format         # pint
composer analyse        # phpstan
```

## Testing

- Pest via Orchestra Testbench; base `Tests\TestCase` (in-memory SQLite, generates PASETO test keys).
- Mock HTTP with `Http::fake()`; never hit real provider APIs in tests.
- Do not create tinker/verification scripts when a test can prove behavior.

## Do not

- Do not change dependencies or add base folders without need.
- Do not create documentation files unless requested (this repo already has `docs/`).
- Do not reintroduce `spatie/laravel-package-tools`.
