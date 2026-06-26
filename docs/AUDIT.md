# Audit & Feature-Tracking Matrix — laranail licensing

Master checklist for the verifier refactor + UI presets + product-updater. Status legend:
**☐** todo · **◐** in-progress · **☑** done · **⊘** deferred (with reason).

Packages: **V**=`laranail/license-verifier` · **PB/PL/PV/PF**=blade/livewire/vue/filament presets ·
**U**=`laranail/product-updater`. Plan: `~/.claude/plans/read-all-files-in-humble-sifakis.md`.

## Conventions & tooling (CONV)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| CONV-01 | Remove spatie/laravel-package-tools; extend laranail/package-tools provider | V | `composer why spatie/laravel-package-tools` empty; provider extends `…Package\Tools\Providers\PackageServiceProvider` | ☑ (install clean; 143 tests pass) |
| CONV-02 | Rename config `licensing-client`→`license-verifier`; env `LICENSE_VERIFIER_*` (+`LICENSING_*` fallback) | V | config publishes as `license-verifier.php`; old env still read | ☑ |
| CONV-03 | Commands → `laranail::license-verifier.*` (+ `license:*` aliases) | V | `artisan list` shows both | ☑ (namespaced names + license:* aliases; verified) |
| CONV-04 | PHP floor `^8.4 || ^8.5`; Rector `php84`; CI 8.4/8.5 | V | green CI both legs | ☑ (floor ^8.4||^8.5; rector php84; tests.yml 8.4/8.5 matrix) |
| CONV-05 | Container alias `LicenseVerifier`; drop the legacy upstream alias | V | alias resolves | ☑ |
| CONV-06 | Rewrite stale CLAUDE.md/README/CHANGELOG; add UPGRADE.md | V | no refs to nonexistent classes | ☑ (CLAUDE/CHANGELOG/UPGRADE/README done) |
| CONV-07 | Standard Simtabi root + .github files present | all | files exist | ☑ (LICENSE/SECURITY/CoC/CONTRIBUTING/.editorconfig/.gitignore/.gitattributes + CI for all packages) |
| CONV-08 | Spelling/slug/namespace consistency across packages | all | grep clean | ☑ (no stray licensing-client/old-facade refs; namespaces consistent) |

## Drivers & capability model (DRV)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| DRV-01 | `Driver` contract + capability sub-interfaces + `UnsupportedByDriverException` | V | interfaces exist; unit test | ☑ (Driver + 6 capability ifaces + Capability enum + VOs; tested) |
| DRV-02 | `DriverManager` (Illuminate Manager) reads `default` + per-driver config; public `extend()` | V | manager test | ☑ (resolves all 14 drivers + extend() tested) |
| DRV-03 | PasetoDriver wraps existing services; all PASETO tests pass via it | V | existing tests green | ☑ (composes engine; 148 tests green) |
| DRV-04 | EnvatoDriver (Botble purchase-code + domain binding + cache/grace) | V | `Http::fake` | ☑ (activate+domain bind tested) |
| DRV-05 | KeygenDriver (validate-key + machine activation; offline crypto) | V | `Http::fake` | ☑ (validate-key tested) |
| DRV-06 | LemonSqueezyDriver | V | `Http::fake` | ☑ (activate tested) |
| DRV-07 | GumroadDriver | V | `Http::fake` | ☑ (verify valid+invalid tested) |
| DRV-08 | CryptolensDriver (RSA-signed → offline) | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-09 | LicenseSpringDriver (hardware_id, features) | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-10 | FreemiusDriver | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-11 | EasyDigitalDownloadsDriver (site-bound) | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-12 | WooCommerceLicenseManagerDriver (lmfwc REST) | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-13 | PaddleDriver | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-14 | UnlockShDriver | V | `Http::fake` | ☑ (Http::fake behavior tested) |
| DRV-15 | GenericHttpDriver (config-mapped endpoints + response field paths) | V | covers Payhip/FastSpring/Appsero/WCKM/Polar/SureCart | ☑ (config-mapped test passing) |
| DRV-16 | NullDriver guarded (refuses in production) | V | unit test | ☑ (prod guard tested) |
| DRV-17 | `Driver::activationFields()` schema consumed by presets | V+presets | schema test | ☑ (blade form+request consume activationFields; tested) |
| DRV-18 | Per-driver `docs/drivers/<name>.md` page | V docs | pages exist | ☑ (consolidated docs/drivers.md) |

## License-detail source & storage (SRC)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| SRC-01 | `LicenseStore` contract + File/Database/Cache/Callback stores | V | round-trip tests | ☑ (4 stores; round-trip tested for db/file/cache) |
| SRC-02 | `LicenseKeyResolver` (env/config/model/closure), `source` config | V | resolver test | ☑ (Config/Model/Callback resolvers; config+model tested) |
| SRC-03 | Wire migration → `LicenseRecord` model, swappable via `models.license` | V | DatabaseStore test | ☑ (model + swap tested) |
| SRC-04 | Real `LicenseRecordFactory` (remove stub) | V | factory test | ☑ (factory used in tests; stub removed) |

## Botble feature ports (PORT)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| PORT-01 | Domain binding + allowed-domains (+ kit domain-claim follow-up) | V | wrong-host rejected | ☑ (DomainBinding incl. subdomain match; tested) |
| PORT-02 | `licensed_to` + activation date in LicenseInfo/model | V | info shows it | ☑ (LicenseInfo + LicenseRecord + ModelKeyResolver details) |
| PORT-03 | Reminder-skip (encrypted timestamp) | V | skip/expire tests | ☑ (ReminderManager; skip/clear/expire tested) |
| PORT-04 | IP resolver + `static_ip` | V | resolver test | ☑ (ThirdPartyIpResolver; static + lookup tested) |
| PORT-05 | Cached connection/health pre-check | V | unit test | ☑ (ConnectionChecker reachable/unreachable tested) |
| PORT-06 | License lifecycle events | V | dispatched assertions | ☑ (9 events + dispatch trait; activate/verify dispatch tested) |

## Security hardening (SEC)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| SEC-01 | Encrypt grace-period + refresh_after at rest | V | file not plaintext | ☑ (encrypt() on store, decrypt-with-fallback on read) |
| SEC-02 | TLS verify on by default for HTTP drivers (`security.verify_tls`) | V | config default true | ☑ (AbstractHttpDriver: verify on unless config false) |
| SEC-03 | NullDriver production guard | V | throws in prod | ☑ (tested) |
| SEC-04 | Constant-time token signature verification retained | V | tamper test | ☑ (TokenValidator certificate-chain tamper tests) |
| SEC-05 | Updater: reject `.env`/product-id/version/min-PHP mismatch in update zip | U | validation test | ☑ (rejects .env + too-small/invalid archive; tested) |

## Bug fixes (BUG)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| BUG-01 | Heartbeat dual-scheduling reconciled | V | single source | ☑ (scheduler triggers; named+withoutOverlapping; shouldSendHeartbeat guards) |
| BUG-02 | Remove unused `ModelFactory` stub | V | replaced | ☑ (replaced by LicenseRecordFactory) |
| BUG-03 | Wire `middleware_groups` | V | applied to groups | ☑ (provider appends CheckLicense to configured groups) |
| BUG-04 | Standardize exception message shape | V | consistent | ☑ (consistent static-factory exceptions: Licensing/UnsupportedByDriver/Updater) |

## CLI / TUI suite (CLI)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| CLI-01 | Commands on package-tools base, `laranail::license-verifier.*` + aliases | V | artisan list | ☑ (12 cmds; verified via list+test) |
| CLI-02 | Lifecycle: activate/deactivate/validate/refresh/info/status (+`--json`/exit codes) | V | feature tests | ☑ (status --json+exit codes tested) |
| CLI-03 | `manage` flagship TUI dashboard | V | TTY render; non-TTY fallback | ☑ (dashboard+askSelect loop; non-TTY fallback) |
| CLI-04 | `watch` live dashboard | V | runs | ☑ (watch with --cycles; tested) |
| CLI-05 | `install` (package-tools InstallCommand) | V | runs | ☑ (hasInstallCommand: publish config/migrations + ask-migrate) |
| CLI-06 | Drivers/source: `drivers`/`driver --test`/`source` | V | output | ☑ (drivers + driver --test + source; tested) |
| CLI-07 | Offline: `fingerprint`/`token:show/export/import`/`keys:public` | V | air-gap round-trip | ☑ (fingerprint + token show/export/import + keys; round-trip tested) |
| CLI-08 | Diagnostics: `ping`/`doctor`/`clear` | V | doctor checks run | ☑ (ping + doctor + clear; tested) |
| CLI-09 | Reminders: `reminder:skip/clear/status` | V | tests | ☑ (consolidated reminder cmd; tested) |
| CLI-10 | Non-interactive/CI safety + exit codes | V | CI test | ☑ (non-TTY fallback + exit codes tested) |
| CLI-11 | `status --strict --json` gates pipelines | V | exit 1 unlicensed | ☑ (tested) |
| CLI-12 | docs/cli.md + docs/tui.md | V docs | exist | ☑ (docs/cli.md + docs/tui.md authored) |

## Presets (PRESET)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| PRESET-01 | blade preset (views/JS/routes/controller/widget, driver-aware) | PB | scratch-app activate | ☑ (installed + 5 feature tests pass) |
| PRESET-02 | livewire preset | PL | components render | ☑ (installed; 3 Livewire tests pass) |
| PRESET-03 | vue preset (Botble SFC port) | PV | mounts | ☑ (installed; 2 Vue tests pass) |
| PRESET-04 | filament preset (page+widget) | PF | panel registers | ☑ (installed on Filament v4; 2 smoke tests pass) |
| PRESET-05 | each uses package-tools + depends on verifier; publishable | all presets | publish works | ☑ (all 4 use package-tools + verifier path dep; composer valid) |

## Updater (UPD)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| UPD-01 | UpdateManager methods ported | U | unit tests | ☑ (checkUpdate/download/extract/validate/clearCaches ported) |
| UPD-02 | UpdateSource contract + HttpUpdateSource | U | `Http::fake` | ☑ (UpdateSource + HttpUpdateSource; checkUpdate Http::fake tested) |
| UPD-03 | ProductRelease VO + Zipper + system-update events | U | tests | ☑ (ProductRelease VO + Zipper + 13 events) |
| UPD-04 | License gating via verifier (`require_license`, RequiresLicenseException) | U | refuses unlicensed | ☑ (refuses unlicensed, allows licensed; tested) |
| UPD-05 | Commands `laranail::product-updater.*` | U | artisan list | ☑ (check+update commands; verified via list) |

## Tests / docs (QA)
| ID | Item | Target | Acceptance | Status |
|----|------|--------|-----------|--------|
| QA-01 | Existing ~115 verifier tests pass post-refactor | V | green | ☑ (201 tests green, 3x stable) |
| QA-02 | New tests: drivers, stores, model, bindings, reminder, IP, events | V | green | ☑ (drivers/stores/model/bindings/reminder/IP/events/CLI tested) |
| QA-03 | Preset feature tests in scratch app | presets | green | ☑ (blade 5 + livewire 3 + vue 2 + filament 2 tests) |
| QA-04 | Updater tests incl. unlicensed-refusal + zip validation | U | green | ☑ (5 updater tests: unlicensed-refusal + .env/zip validation) |
| QA-05 | docs/ pages (install/config/architecture/security/drivers/presets/updater) | all | exist | ☑ (verifier README + cli/tui/drivers docs; READMEs for all 5 new packages) |

---

## Remediation pass (post-build code audit)

The matrix above tracked **build completion**. A subsequent code audit found that several
"ported" features were wired-but-dead — most importantly, the documented API (facade,
middleware, commands, scheduler) called the hardwired PASETO engine and **ignored
`config('default')`**, so the driver layer only ran through `DriverManager->active()`.
This section tracks the fixes. All land on `refactor/laranail-headless-verifier`.

| ID | Finding (audit) | Fix | Status |
|----|-----------------|-----|--------|
| REM-A | Facade/middleware/commands/scheduler bypass the driver layer (PASETO hardwired) | New `LicenseManager` (Manager + `ForwardsCalls`) as the single entry point; repoint facade, `CheckLicense`, lifecycle commands and the heartbeat scheduler; centralise lifecycle events so every driver fires them; keep the PASETO engine (and its 54 tests) as `PasetoDriver`'s backend | ☑ (`ProviderAgnosticTest` proves facade + middleware exercise the active driver — null/Gumroad — not PASETO) |
| REM-B | Static analysis never run (baseline referenced a deleted config) | Repair `phpstan*.neon*`; fix real issues; apply pint + rector (php84) | ☑ (phpstan **No errors**; pint/rector clean) |
| REM-C | HTTP drivers had no offline grace; `security.fail_open_in_grace` was dead config | Cache the last successful result; serve it as **Grace** within the window (fail-open), fail-closed after; invalidate on activate/deactivate | ☑ (`ResilienceTest`) |
| REM-D | Dual storage; cache token + db metadata stored in plaintext; updater `licenseToken()` null for PASETO | Encryption pipeline: token + metadata ciphertext at rest on **file/db/cache**; `LicenseManager::currentToken()`/`licensedTo()`; updater reads `currentToken()` | ☑ (`EncryptionPipelineTest` asserts ciphertext at rest) |
| REM-E | `Bindings\DomainBinding` built/bound but never invoked | Enforced inside `LicenseManager::verify()` (config allowlist or the driver's `boundDomains()`); a usable result on a disallowed host is downgraded to Invalid | ☑ (`ResilienceTest` domain case) |
| REM-F | CLI output content untested; a `--json` branch was inconsistent | `wantsJson` reads input directly; content tests (source/driver/drivers/doctor) via a stable capture path | ☑ |
| REM-G | HTTP drivers never dispatched events; preset `deactivate`/`status` ungated; no retry/backoff; PASETO ignored `verify_tls` | Events centralised (all drivers fire them); permission gate on blade/vue mutating endpoints; retry+backoff on HTTP drivers + updater download; `LicensingApiClient` honors `verify_tls` | ☑ |
| REM-H | i18n namespace mismatch — **all** translated strings (verifier + 4 presets) fell through to raw keys | Register the short `license-verifier::` namespace explicitly | ☑ (`TranslationTest` asserts resolution) |
| REM-I | Stale/duplicated CI workflows; broken phpstan baseline | Consolidated to `tests.yml` (8.4/8.5) + `static-analysis.yml` (pint/phpstan/rector) | ☑ |
| REM-SEAT | No seat list/revoke surface | `SupportsSeatManagement` + `PasetoDriver`; `LicenseManager` seat methods; `license:seats` command; kit `UsageController@index/@revoke` + routes | ☑ (verifier `SeatManagementTest`; kit API tests) |
| REM-DOC | `AUDIT.md` read "all done"; docs lacked diagrams | This reconciliation + Mermaid-first `architecture.md`/`security.md` + README docs-index sections | ☑ |

**Verifier suite after remediation: 223 passing; phpstan/pint/rector clean.** The PASETO
engine's 54 direct-construction unit tests remain untouched and green.

[← Docs index](../README.md#documentation)
