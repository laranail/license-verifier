# Drivers

Set the active driver with `license-verifier.default` (env `LICENSE_VERIFIER_DRIVER`). Each
driver reads its own block under `license-verifier.drivers.<name>`. All HTTP drivers verify TLS
by default (`license-verifier.security.verify_tls`).

[← Docs index](../README.md#documentation)

Optional capabilities: **O** offline tokens · **R** refresh · **H** heartbeat ·
**E** entitlements · **S** seats · **SM** seat management · **D** domain binding. Every driver
supports the core verbs (activate / verify / deactivate) regardless; the column below lists only
the *optional* capability interfaces a driver actually implements (it is derived from those
interfaces, so it can never overstate — see "Capability model").

| Driver | Service | Caps | Key config |
|---|---|---|---|
| `paseto` *(default)* | self-hosted `laranail/license-kit` | O R H E S SM D | `server_url`, `public_key`, `issuer` |
| `envato` | Envato / CodeCanyon (Botble-style server) | D | `server_url`, `api_key`, `product_id` |
| `keygen` | keygen.sh | — | `account`, `base_url` |
| `lemonsqueezy` | Lemon Squeezy | — | `store_id` |
| `gumroad` | Gumroad | — | `product_id`, `access_token` |
| `cryptolens` ¹ | Cryptolens | — | `token`, `product_id` |
| `licensespring` ¹ | LicenseSpring | — | `api_key`, `product` |
| `freemius` ¹ | Freemius | — | `product_id`, `base_url` |
| `edd` | EDD Software Licensing | D | `store_url`, `item_id` |
| `woocommerce` | License Manager for WooCommerce | — | `store_url`, `consumer_key/secret` |
| `paddle` | Paddle | — | `api_key`, `sandbox` |
| `unlocksh` | unlock.sh | — | `api_key`, `base_url` |
| `whop` | Whop.com memberships | — | `api_key`, `product_id` |
| `anystack` | Anystack.sh | D | `api_key`, `product_id` |
| `generic` | any bespoke HTTP service | configurable | `endpoints`, `response_map` |
| `null` | dev/testing | — | refuses in production |

> ¹ **Request signing:** `cryptolens` verifies the RSA-SHA256 signed response (fail-closed) when a
> `public_key` is set — accepts a PEM key or the Cryptolens `<RSAKeyValue>` XML form.
> `licensespring` signs requests with the HMAC-SHA256 "Date" signature when `shared_key` is set;
> `freemius` signs with the FS-Auth scheme when `secret_key` is set. Each engages automatically
> once its key is configured; without it the driver falls back to unsigned (mock/proxy) calls.
>
> **Provider deactivation:** all marketplace drivers release the seat/machine/install on the
> provider on `deactivate()` (best-effort — local state is always cleared even if the call fails).
>
> **Domain binding:** `envato`/`edd` bind to the host the license was **activated** on (read back
> from the stored record), so the orchestrator rejects a different host. The config allowlist
> (`bindings.domain.allowed`) applies on top, to every driver.

## Capability model

A driver implements only the capability interfaces it supports
(`Contracts\Capabilities\Supports*`), and `capabilities()` is **derived** from those interfaces
in `AbstractHttpDriver` — so it can never advertise something the driver doesn't back. The
orchestrator checks the same interfaces before delegating (no-op / empty / `false` for
unsupported optional verbs) — so switching providers never silently drops behaviour.

## Generic driver

Map any service without code via dot-path response mapping:

```php
'generic' => [
    'base_url' => 'https://api.example.com',
    'endpoints' => ['validate' => ['method' => 'POST', 'path' => '/check']],
    'response_map' => ['valid' => 'meta.valid', 'status' => 'data.status', 'expires_at' => 'data.expires_at'],
],
```

Covers Payhip, FastSpring, Appsero, WC Key Manager, Polar.sh, SureCart, and custom servers.

## Custom drivers

```php
app(\Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager::class)
    ->extend('my-service', fn ($app) => new MyDriver($app['config']->get('license-verifier.drivers.my-service')));
```

## Runtime configuration

Everything is config-driven (`config/license-verifier.php`, env `LICENSE_VERIFIER_*`) and can also
be changed at **runtime** through the facade:

```php
use Simtabi\Laranail\Licence\Verifier\Facades\LicenseVerifier;

// Verify against a non-default provider for one call, without changing the global default:
LicenseVerifier::driver('gumroad')->verify($key);

// Apply config overrides and have them take effect immediately:
LicenseVerifier::configure([
    'default' => 'keygen',
    'drivers.keygen.account' => 'acct_123',
    'storage.driver' => 'database',
])->isValid();

// After a manual config() change, re-resolve the frozen services (drivers, store, key source):
config()->set('license-verifier.storage.driver', 'cache');
LicenseVerifier::reload();
```

`driver()` returns a scoped manager (no global change); `configure()`/`reload()` flush the
driver cache, `LicenseStore`, and the key resolver so subsequent calls pick up the new config.
Transport settings (`timeout`, `verify_tls`, `retries`, `retry_delay`) may also be overridden
per-driver inside that driver's config block.

[← Docs index](../README.md#documentation)
