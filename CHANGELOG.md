# Changelog

All notable changes to `laranail/license-verifier` will be documented in this file.

## Unreleased

Hard fork under the laranail org. The package is now a headless, provider-agnostic
license verification core. See `UPGRADE.md` for migration steps.

### Changed (BREAKING)
- Replaced `spatie/laravel-package-tools` with `laranail/package-tools`.
- Config file renamed `licensing-client.php` → `license-verifier.php`.
- Environment variables renamed `LICENSING_*` → `LICENSE_VERIFIER_*` (old vars still
  read as a fallback for one minor; see `UPGRADE.md`).
- Container/Facade alias renamed to `LicenseVerifier`.
- Service provider moved to `Simtabi\Laranail\Licence\Verifier\Providers\LicenceVerifierServiceProvider`.
- Facade class renamed to `Simtabi\Laranail\Licence\Verifier\Facades\LicenceVerifier`.
- Database table renamed `licensing_client_cache` → `license_verifier_licenses`.
- PHP floor raised to `^8.4 || ^8.5`; `paragonie/paseto` bumped to `^3.5`.
- Doctor/config/i18n standardized on package-tools' enhanced doctor: a reusable check library
  plus shared `DoctorReporter`/`HealthResponder` (the `doctor` command and `/health` endpoint are
  thin shells over a single `Doctor\Checks::all()`); `->withoutConfigNamespacing()` so config
  defaults resolve under `license-verifier.*`; `->hasTranslations('license-verifier')` replaces the
  manual translation-namespace shim.

### Added (in progress)
- Provider-agnostic driver layer with a capability model (PASETO default + marketplace
  and commerce drivers). Configurable license-detail source + Eloquent model. CLI/TUI
  command suite. See `docs/AUDIT.md` for the full tracking matrix.

## 2.0.0 - 2026-04-08

### Features
- Certificate chain verification (Ed25519) for offline key rotation support
- Public key bundle application from server responses to token validator
- Token validator initialization from stored bundle on boot
- PASETO issuer claim (`iss`) validation
- Clock skew tolerance (±60s, configurable) for token expiration and not-before checks
- Not-before (`nbf`) claim validation
- Entitlements storage and retrieval API
- Additional token claims exposed: `licensable_type`, `licensable_id`, `not_before`, `issuer`, `entitlements`

### Improvements
- Improved HTTP error code mapping (400, 5xx status codes)
- Full compatibility with `laravel-licensing` server v2.0.0

### CI
- Added PHP 8.5 to test matrix
- Added Laravel 13 to test matrix

### Breaking Changes
- Tokens with invalid issuer are now rejected (configure `licensing-client.issuer` to match your server)
- Tokens with certificate chain in footer are now verified against the root public key

**Full Changelog**: https://github.com/laranail/license-verifier/compare/v1.0.0...2.0.0

## 1.0.0 - 2025-09-16

**Full Changelog**: https://github.com/laranail/license-verifier/commits/v1.0.0
