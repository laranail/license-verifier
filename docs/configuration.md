# Configuration

Every key in `config/license-verifier.php`, its env var (prefix `LICENSE_VERIFIER_*`; the legacy
`LICENSING_*` prefix is read as a fallback), and what it controls.

```bash
php artisan vendor:publish --tag=license-verifier-config
```

## Driver selection

| Key | Env | Default | What |
|-----|-----|---------|------|
| `default` | `LICENSE_VERIFIER_DRIVER` | `paseto` | The active driver — see [Drivers](tools/drivers.md) for the 14 built-ins and their per-driver blocks under `drivers.<name>`. |
| `sources` | `LICENSE_VERIFIER_SOURCES` | `[]` | Optional ordered driver list for `LicenseManager::activateAcross()` — the first source that accepts the credentials wins (products sold through several channels). |

## License key and server

| Key | Env | What |
|-----|-----|------|
| `license_key` | `LICENSE_VERIFIER_KEY` | The license key for this installation (when `source` is `config`). |
| `licensed_to` | `LICENSE_VERIFIER_LICENSED_TO` | Display name of the licensee. |
| `public_key` | `LICENSE_VERIFIER_PUBLIC_KEY` | Ed25519 public key for offline token verification (crypto drivers). |
| `server_url` / `api_version` / `issuer` | `LICENSE_VERIFIER_SERVER_URL` / `…_API_VERSION` / `…_ISSUER` | Connection settings for the default `paseto` driver (`laranail/license-kit`). |
| `timeout` / `clock_skew_seconds` / `grace_period_days` | `…_TIMEOUT` / `…_CLOCK_SKEW_SECONDS` / `…_GRACE_PERIOD_DAYS` | Transport timeout, allowed clock skew, and the offline grace window (default 7 days). |

## License-detail source

`source` (`LICENSE_VERIFIER_SOURCE`, default `config`) chooses where the key/details are
resolved from:

- `config` — read `license_key` from this config/env.
- `model` — read from the Eloquent model below (database-backed installs).
- `callback` — resolve via a closure bound as `license-verifier.resolver`.

The `LicenseRecord` model is swappable via `models.license`.

## Storage

`storage` governs where the activated record and the offline token live — encrypted at rest on
every backend (see [Security](security.md)):

| Key | Env | Default | What |
|-----|-----|---------|------|
| `storage.driver` | `LICENSE_VERIFIER_STORAGE` | `file` | `file`, `database`, `cache`, or `callback`. |
| `storage.fallback` | `…_STORAGE_FALLBACK` | `file` | Encrypted local fallback when a remote primary (`database`/`cache`) is unreachable; `null` disables. |
| `storage.fallback_cooldown` | `…_STORAGE_FALLBACK_COOLDOWN` | `15` | Seconds to keep serving the fallback before re-probing the primary (circuit breaker). |
| `storage.path` | `…_STORAGE_PATH` | `storage/app/licensing` | Location of the file store. |

## Caching, events, heartbeat

| Block | Keys | What |
|-------|------|------|
| `cache` | `enabled`, `store`, `ttl`, `key_prefix` | Verification-result cache (default on, TTL 3600s) — the basis of offline grace. |
| `events` | `enabled` | Dispatch the lifecycle events (`LicenseActivated`, `LicenseVerified`, …); off silences all license events app-wide. |
| `heartbeat` | `enabled`, `interval` | Periodic server heartbeat for drivers that support it (default hourly, scheduled automatically). |

## Enforcement

| Block | Keys | What |
|-------|------|------|
| `bindings.domain` | `enabled`, `allowed` | Domain binding: verify the app host against the config allowlist and/or the driver's bound domains; a mismatch downgrades the result to Invalid. |
| `middleware_groups` | `LICENSE_VERIFIER_MIDDLEWARE_GROUPS` | Middleware groups to auto-append `CheckLicense` to (the `license` route alias is always registered). |
| `excluded_routes` | — | Route patterns the middleware skips (`login`, `license/*`, …). |
| `rate_limit` | `enabled`, `max_attempts`, `decay_seconds` | Opt-in per-key throttle around activation (anti-brute-force). |
| `audit` | `enabled`, `channel` | Opt-in append-only audit log of activation outcomes (provenance only — never the raw key). |

## Security and transport

| Key | Default | What |
|-----|---------|------|
| `security.verify_tls` | `true` | TLS verification for all HTTP drivers and the PASETO API client. |
| `security.fail_open_in_grace` | `true` | Serve the last good result within the grace window when the source is unreachable; `false` fails closed immediately. |
| `security.retries` / `security.retry_delay` | `2` / `200` | Transient-failure retry policy (connection errors + 5xx, linear backoff). |

## Miscellaneous

| Block | What |
|-------|------|
| `reminder.default_skip_days` | Default length of a reminder skip (`license:reminder skip`). |
| `ip` | Static IP override or lookup URL/timeout for device fingerprinting. |
| `debug` | Verbose diagnostics. |
| `api` | Opt-in health endpoint — `GET {prefix}/health` returns the doctor checks as JSON (`200` healthy / `503` degraded) for monitoring. Off by default. |

## Runtime overrides

Config can also be changed at runtime — `LicenseVerifier::driver()` for a one-call scope,
`configure()` / `reload()` to re-resolve the frozen services. See
[Drivers — runtime configuration](tools/drivers.md#runtime-configuration).

---

[← Docs index](../README.md#documentation)
