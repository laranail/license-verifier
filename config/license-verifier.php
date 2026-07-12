<?php

declare(strict_types=1);
use Simtabi\Laranail\Licence\Verifier\Models\LicenseRecord;

/*
|--------------------------------------------------------------------------
| laranail/license-verifier configuration
|--------------------------------------------------------------------------
|
| Env vars use the LICENSE_VERIFIER_* prefix. For backward compatibility
| with the pre-rename releases, the old LICENSING_* vars are still read as
| a fallback (this fallback will be removed in a future minor — see UPGRADE.md).
|
*/

$env = (static fn (string $key, mixed $default = null): mixed => env('LICENSE_VERIFIER_'.$key, env('LICENSING_'.$key, $default)));

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    | Which license source the verifier talks to. Built-in drivers:
    | paseto (laranail/license-kit, default), envato, keygen, lemonsqueezy,
    | gumroad, cryptolens, licensespring, freemius, edd, woocommerce, paddle,
    | unlocksh, generic, null. Register custom drivers via the DriverManager
    | (Laravel passes only the container to the creator):
    |   app(DriverManager::class)->extend('name',
    |       fn ($app) => new MyDriver(config('license-verifier.drivers.name', []))).
    */
    'default' => $env('DRIVER', 'paseto'),

    /*
    |--------------------------------------------------------------------------
    | Multi-source activation
    |--------------------------------------------------------------------------
    | Optional ordered list of driver names tried by LicenseManager::activateAcross()
    | — the first source that accepts the credentials wins (for products sold
    | through several channels). Empty = single-driver activation via `default`.
    */
    'sources' => array_values(array_filter(explode(',', (string) $env('SOURCES', '')))),

    /*
    |--------------------------------------------------------------------------
    | Activation rate limiting
    |--------------------------------------------------------------------------
    | Opt-in per-key throttle around activation (anti-brute-force). Off by default.
    */
    'rate_limit' => [
        'enabled' => (bool) $env('RATE_LIMIT', false),
        'max_attempts' => (int) $env('RATE_LIMIT_MAX', 5),
        'decay_seconds' => (int) $env('RATE_LIMIT_DECAY', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Activation audit log
    |--------------------------------------------------------------------------
    | Opt-in append-only audit of activation outcomes (provenance only — never the
    | raw key) to the given log channel (null = default).
    */
    'audit' => [
        'enabled' => (bool) $env('AUDIT', false),
        'channel' => $env('AUDIT_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared license key / public key
    |--------------------------------------------------------------------------
    | The license key for this installation, and (for crypto drivers) the
    | Ed25519 public key used to verify offline tokens.
    */
    'license_key' => $env('KEY'),
    'licensed_to' => $env('LICENSED_TO'),
    'public_key' => $env('PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Driver connection settings (PASETO / license-kit defaults)
    |--------------------------------------------------------------------------
    | Kept at the top level for the default paseto driver. Other drivers read
    | their own block under `drivers.*` below.
    */
    'server_url' => $env('SERVER_URL', 'https://licensing.example.com'),
    'api_version' => $env('API_VERSION', 'v1'),
    'issuer' => $env('ISSUER', 'laravel-licensing'),
    'timeout' => (int) $env('TIMEOUT', 30),
    'clock_skew_seconds' => (int) $env('CLOCK_SKEW_SECONDS', 60),
    'grace_period_days' => (int) $env('GRACE_PERIOD_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Per-driver configuration
    |--------------------------------------------------------------------------
    | Each driver reads `drivers.<name>`. Secrets come from env. Drivers that
    | are not configured simply stay unavailable until their block is filled.
    */
    'drivers' => [

        'paseto' => [
            'server_url' => $env('SERVER_URL', 'https://licensing.example.com'),
            'api_version' => $env('API_VERSION', 'v1'),
            'issuer' => $env('ISSUER', 'laravel-licensing'),
            'public_key' => $env('PUBLIC_KEY'),
        ],

        'envato' => [
            // Marketplace / license server (Botble-style). LB-* headers.
            'server_url' => $env('ENVATO_SERVER_URL'),
            'api_key' => $env('ENVATO_API_KEY'),
            'personal_token' => $env('ENVATO_PERSONAL_TOKEN'),
            'product_id' => $env('ENVATO_PRODUCT_ID'),
            'verify_type' => $env('ENVATO_VERIFY_TYPE', 'envato'),
        ],

        'keygen' => [
            'account' => $env('KEYGEN_ACCOUNT'),
            'product' => $env('KEYGEN_PRODUCT'),
            'base_url' => $env('KEYGEN_BASE_URL', 'https://api.keygen.sh'),
            'public_key' => $env('KEYGEN_PUBLIC_KEY'),
        ],

        'lemonsqueezy' => [
            'store_id' => $env('LEMONSQUEEZY_STORE_ID'),
            'base_url' => $env('LEMONSQUEEZY_BASE_URL', 'https://api.lemonsqueezy.com'),
        ],

        'gumroad' => [
            'product_id' => $env('GUMROAD_PRODUCT_ID'),
            'product_permalink' => $env('GUMROAD_PRODUCT_PERMALINK'),
            'access_token' => $env('GUMROAD_ACCESS_TOKEN'),
            'base_url' => $env('GUMROAD_BASE_URL', 'https://api.gumroad.com'),
        ],

        // Request signing engages automatically once the relevant key is set; any
        // driver block may also override transport: timeout/verify_tls/retries/retry_delay.
        'cryptolens' => [
            'token' => $env('CRYPTOLENS_TOKEN'),
            'product_id' => $env('CRYPTOLENS_PRODUCT_ID'),
            'public_key' => $env('CRYPTOLENS_RSA_PUBLIC_KEY'), // PEM or Cryptolens XML; verifies the signed response (fail-closed)
            'base_url' => $env('CRYPTOLENS_BASE_URL', 'https://api.cryptolens.io'),
        ],

        'licensespring' => [
            'api_key' => $env('LICENSESPRING_API_KEY'),
            'shared_key' => $env('LICENSESPRING_SHARED_KEY'), // enables the HMAC-SHA256 "Date" request signature
            'product' => $env('LICENSESPRING_PRODUCT'),
            'base_url' => $env('LICENSESPRING_BASE_URL', 'https://api.licensespring.com'),
        ],

        'freemius' => [
            'product_id' => $env('FREEMIUS_PRODUCT_ID'),
            'public_key' => $env('FREEMIUS_PUBLIC_KEY'),
            'secret_key' => $env('FREEMIUS_SECRET_KEY'), // enables FS-Auth request signing
            'base_url' => $env('FREEMIUS_BASE_URL', 'https://api.freemius.com'),
        ],

        'edd' => [
            'store_url' => $env('EDD_STORE_URL'),
            'item_id' => $env('EDD_ITEM_ID'),
            'item_name' => $env('EDD_ITEM_NAME'),
        ],

        'woocommerce' => [
            'store_url' => $env('WOOCOMMERCE_STORE_URL'),
            'consumer_key' => $env('WOOCOMMERCE_CONSUMER_KEY'),
            'consumer_secret' => $env('WOOCOMMERCE_CONSUMER_SECRET'),
        ],

        'paddle' => [
            'api_key' => $env('PADDLE_API_KEY'),
            'product_id' => $env('PADDLE_PRODUCT_ID'),
            'sandbox' => (bool) $env('PADDLE_SANDBOX', false),
        ],

        'unlocksh' => [
            'api_key' => $env('UNLOCKSH_API_KEY'),
            'product_id' => $env('UNLOCKSH_PRODUCT_ID'),
            'base_url' => $env('UNLOCKSH_BASE_URL', 'https://api.unlock.sh'),
        ],

        'whop' => [
            'api_key' => $env('WHOP_API_KEY'),
            'product_id' => $env('WHOP_PRODUCT_ID'),
            'base_url' => $env('WHOP_BASE_URL', 'https://api.whop.com'),
        ],

        'anystack' => [
            'api_key' => $env('ANYSTACK_API_KEY'),
            'product_id' => $env('ANYSTACK_PRODUCT_ID'),
            'base_url' => $env('ANYSTACK_BASE_URL', 'https://api.anystack.sh/v1'),
        ],

        // Configurable escape hatch — map any bespoke HTTP service without code.
        'generic' => [
            'base_url' => $env('GENERIC_BASE_URL'),
            'headers' => [],
            'endpoints' => [
                // 'activate' => ['method' => 'POST', 'path' => '/activate'],
                // 'validate' => ['method' => 'POST', 'path' => '/validate'],
                // 'deactivate' => ['method' => 'POST', 'path' => '/deactivate'],
                // 'health' => ['method' => 'GET', 'path' => '/health'],
            ],
            // Dot-path mapping from the response JSON to normalized fields.
            'response_map' => [
                'valid' => 'meta.valid',
                'status' => 'data.status',
                'expires_at' => 'data.expires_at',
                'entitlements' => 'data.entitlements',
            ],
        ],

        'null' => [
            // Always-valid dev driver. Refuses to run in production unless allowed.
            'allow_in_production' => (bool) $env('ALLOW_NULL', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | License-detail source
    |--------------------------------------------------------------------------
    | Where the license key/details are resolved from:
    |   config   — read `license_key` from this config/env
    |   model    — read from the Eloquent model below (DatabaseStore)
    |   callback — resolve via a closure bound as 'license-verifier.resolver'
    */
    'source' => $env('SOURCE', 'config'),

    /*
    |--------------------------------------------------------------------------
    | Storage of the activated license / token
    |--------------------------------------------------------------------------
    | store: file (default, encrypted), database, cache, callback.
    |
    | When the primary is a remote backend (database/cache) that becomes
    | unreachable, the client transparently degrades to the encrypted local
    | `fallback` store (set to null to disable). `fallback_cooldown` is how long
    | (seconds) to keep serving the fallback after a connection failure before
    | re-probing the primary.
    */
    'storage' => [
        'driver' => $env('STORAGE', 'file'),
        'fallback' => $env('STORAGE_FALLBACK', 'file'),
        'fallback_cooldown' => (int) $env('STORAGE_FALLBACK_COOLDOWN', 15),
        'path' => $env('STORAGE_PATH') ?: storage_path('app/licensing'),
    ],
    // Back-compat alias used by TokenStorage.
    'storage_path' => $env('STORAGE_PATH') ?: storage_path('app/licensing'),

    /*
    |--------------------------------------------------------------------------
    | Swappable Eloquent models (used by the database store/resolver)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'license' => LicenseRecord::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => (bool) $env('CACHE_ENABLED', true),
        'store' => $env('CACHE_STORE', 'file'),
        'ttl' => (int) $env('CACHE_TTL', 3600),
        'key_prefix' => (string) $env('CACHE_KEY_PREFIX', 'license-verifier'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    | Dispatch lifecycle events (LicenseActivated/Verified/Deactivated/…). Turn
    | off to silence all license events application-wide.
    */
    'events' => [
        'enabled' => (bool) $env('EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat (drivers that support it)
    |--------------------------------------------------------------------------
    */
    'heartbeat' => [
        'enabled' => (bool) $env('HEARTBEAT_ENABLED', true),
        'interval' => (int) $env('HEARTBEAT_INTERVAL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bindings (anti-piracy enforcement)
    |--------------------------------------------------------------------------
    | domain: bind/verify the app host. For PASETO this needs the kit to emit
    | a `domain` claim; until then `allowed` acts as a config allowlist.
    */
    'bindings' => [
        'domain' => [
            'enabled' => (bool) $env('BIND_DOMAIN', false),
            'allowed' => array_filter(explode(',', (string) $env('ALLOWED_DOMAINS', ''))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder (nag-skip)
    |--------------------------------------------------------------------------
    */
    'reminder' => [
        'default_skip_days' => (int) $env('REMINDER_SKIP_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP resolution
    |--------------------------------------------------------------------------
    */
    'ip' => [
        'static_ip' => $env('STATIC_IP'),
        'lookup_url' => $env('IP_LOOKUP_URL', 'https://ipecho.net/plain'),
        'lookup_timeout' => (int) $env('IP_LOOKUP_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        // TLS verification for HTTP drivers — ON by default.
        'verify_tls' => (bool) $env('VERIFY_TLS', true),
        // Fail open within the grace window when the server is unreachable.
        'fail_open_in_grace' => (bool) $env('FAIL_OPEN_IN_GRACE', true),
        // Transient-failure retry policy for HTTP drivers.
        'retries' => (int) $env('HTTP_RETRIES', 2),
        'retry_delay' => (int) $env('HTTP_RETRY_DELAY', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | The `license` route-middleware alias is always registered. Optionally
    | auto-apply CheckLicense to these middleware groups.
    */
    'middleware_groups' => array_filter(explode(',', (string) $env('MIDDLEWARE_GROUPS', ''))),

    'excluded_routes' => [
        'login',
        'register',
        'password/*',
        'licensing/*',
        'license/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) $env('DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Health endpoint (opt-in)
    |--------------------------------------------------------------------------
    |
    | Off by default — this is a headless client. When enabled, GET
    | {prefix}/health returns the doctor checks as JSON (200 healthy /
    | 503 degraded) for monitoring.
    */
    'api' => [
        'enabled' => (bool) $env('API_ENABLED', false),
        'prefix' => (string) $env('API_PREFIX', 'api/license-verifier/v1'),
        'middleware' => ['api'],
    ],
];
