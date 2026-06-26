# Architecture

`laranail/license-verifier` is a **headless, provider-agnostic** license client. The
documented API (facade, middleware, Artisan commands, scheduler) flows through a single
orchestrator — `LicenseManager` — which delegates to the configured **driver**. PASETO is
just the default driver; set `license-verifier.default` to any of the 14 drivers and the
whole API follows.

## Ecosystem at a glance

```mermaid
graph LR
    subgraph server[Server side]
        KIT["laranail/license-kit<br/>(issuer + seat registry)"]
    end
    subgraph app[Your Laravel app]
        V["laranail/license-verifier<br/>(headless client)"]
        UPD["laranail/product-updater<br/>(license-gated)"]
        subgraph presets[UI presets]
            PB[blade]
            PL[livewire]
            PV[vue]
            PF[filament]
        end
    end
    PB --> V
    PL --> V
    PV --> V
    PF --> V
    UPD -->|verify + currentToken| V
    V <-->|activate / verify / heartbeat / usages| KIT
    EXT["External providers<br/>Envato · Keygen · Gumroad · …"]
    V <-->|driver HTTP| EXT
```

## Internals — the orchestrator and the driver layer

```mermaid
graph TD
    F["Facade: LicenseVerifier"] --> LM
    MW["CheckLicense middleware"] --> LM
    CMD["Artisan commands<br/>activate / verify / seats / …"] --> LM
    SCH["Heartbeat scheduler"] --> LM
    LM["LicenseManager<br/>(Manager + ForwardsCalls)"]
    LM -->|active()| DM["DriverManager"]
    LM -->|cache + grace| CACHE[("verification cache")]
    LM -->|enforce| DB["DomainBinding"]
    LM -->|dispatch| EV["Lifecycle events"]
    DM --> D1["PasetoDriver"] --> ENG["LicenceVerifier engine<br/>(PASETO v4 / Ed25519)"]
    DM --> D2["HTTP drivers ×12<br/>Envato · Keygen · LemonSqueezy · Gumroad<br/>Cryptolens · LicenseSpring · Freemius · EDD<br/>WooCommerce · Paddle · unlock.sh · generic"]
    DM --> D3["NullDriver"]
    ENG --> TS["TokenStorage<br/>(PASETO packing)"]
    TS --> LS
    D2 --> LS[("LicenseStore<br/>file · database · cache<br/>+ resilient fallback")]
```

All persisted state — including the PASETO token, which `TokenStorage` now packs through the
same `LicenseStore` — is encrypted at rest and governed by `storage.driver`, with an encrypted
local file fallback when a remote primary is unreachable. See
[security.md](security.md#resilient-tiered-storage) for the write/read failover flow.

Each driver advertises **capabilities** via small interfaces; the manager capability-gates
the optional verbs (no-op or `UnsupportedByDriverException` when unsupported):

| Capability interface | Verbs | Declared by (e.g.) |
|----------------------|-------|--------------------|
| `SupportsOfflineTokens` | `requiresOnlineRefresh` | paseto |
| `SupportsRefresh` | `refresh` | paseto, keygen, lemonsqueezy |
| `SupportsHeartbeat` | `heartbeat` | paseto, licensespring |
| `SupportsEntitlements` | `entitlements`, `entitledTo` | paseto, cryptolens, freemius |
| `SupportsSeats` | `seatsUsed`, `seatsTotal` | paseto, keygen |
| `SupportsSeatManagement` | `listSeats`, `revokeSeat` | paseto |
| `SupportsDomainBinding` | `boundDomains` | envato, edd, woocommerce |

## Activation (online)

```mermaid
sequenceDiagram
    actor U as App
    participant LM as LicenseManager
    participant D as Active driver
    participant S as Provider / kit
    U->>LM: activate(key, client)
    LM->>LM: dispatch LicenseActivating
    LM->>D: activate(LicenseRequest)
    D->>S: POST activate (key + fingerprint)
    S-->>D: token / purchase record
    D->>D: persist (encrypted) + licensed_to
    D-->>LM: VerificationResult(valid)
    LM->>LM: invalidate verify-cache
    LM->>LM: dispatch LicenseActivated
    LM-->>U: VerificationResult
```

## Verify with cache + grace (offline resilience)

```mermaid
sequenceDiagram
    participant MW as CheckLicense
    participant LM as LicenseManager
    participant C as Verification cache
    participant D as Active driver
    MW->>LM: verify()
    LM->>C: fresh cached result?
    alt fresh hit
        C-->>LM: cached result
    else miss / stale
        LM->>D: verify()
        alt driver reachable
            D-->>LM: result
            LM->>C: cache if usable (last-good)
        else unreachable / 5xx
            LM->>C: last-good within grace window?
            alt within grace AND fail_open_in_grace
                C-->>LM: serve as Grace (usable)
            else
                LM-->>MW: Unreachable (fail closed)
            end
        end
    end
    LM->>LM: enforce domain binding
    LM-->>MW: VerificationResult
```

## Middleware gating

```mermaid
flowchart TD
    R[Request] --> EX{Excluded route?}
    EX -->|yes| PASS[next]
    EX -->|no| V["LicenseManager::verify()"]
    V --> U{isUsable?}
    U -->|no| DENY["403 / JSON LICENSE_INVALID"]
    U -->|yes| HB[heartbeat]
    HB --> SOON{expiring soon?}
    SOON -->|yes| FLAG[set request attributes]
    SOON -->|no| PASS
    FLAG --> PASS
```

## License status lifecycle

```mermaid
stateDiagram-v2
    [*] --> Unactivated
    Unactivated --> Valid: activate
    Valid --> Grace: source unreachable (within window)
    Grace --> Valid: source reachable again
    Grace --> Unreachable: grace window elapsed
    Valid --> Expired: past expiry
    Valid --> Revoked: deactivate / seat revoked
    Unreachable --> Valid: source reachable again
    Expired --> [*]
    Revoked --> [*]
```

See also: [security.md](security.md) (encryption pipeline + threat model),
[drivers.md](drivers.md), [cli.md](cli.md), [tui.md](tui.md).

[← Docs index](../README.md#documentation)
