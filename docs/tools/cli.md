# CLI reference

All commands are namespaced `laranail::license-verifier.*` with short `license:*` aliases.
They share the `laranail/console` services (`$this->services->interaction()`, `display()`),
support `--json` where noted, and return CI-friendly exit codes.

## Exit codes

`0` valid / grace (or success) · `1` invalid / expired / unactivated · `2` server unreachable
(unreachable is `0` unless `--strict`) · `>2` usage/error.

## Lifecycle

| Command | Alias | Notes |
|---|---|---|
| `…​.activate {key?}` | `laranail::license-verifier.activate` | Activates; prints license table. |
| `…​.deactivate` | `laranail::license-verifier.deactivate` | Confirms, then releases the activation. |
| `…​.validate` | `laranail::license-verifier.validate` | Offline validity check; warns when expiring soon. |
| `…​.refresh` | `laranail::license-verifier.refresh` | Pulls a fresh token (drivers that support it). |
| `…​.info` | `laranail::license-verifier.info` | Full license detail table. |
| `…​.status {--strict} {--json}` | `laranail::license-verifier.status` | **CI gate** — exit code reflects validity. |

```bash
php artisan laranail::license-verifier.status --strict --json   # block a deploy when unlicensed
```

## Interactive

| Command | Alias | Notes |
|---|---|---|
| `…​.manage` | `license`, `laranail::license-verifier.manage` | TUI dashboard: status panel + action menu. Non-TTY → `status`. |

## Drivers, source & diagnostics

| Command | Alias | Notes |
|---|---|---|
| `…​.drivers {--json}` | `laranail::license-verifier.drivers` | Capability table of all drivers (active marked). |
| `…​.driver {name?} {--test} {--json}` | `laranail::license-verifier.driver` | Inspect one driver (capabilities; `--test` pings health). |
| `…​.source {--json}` | `laranail::license-verifier.source` | Show the configured license-detail source + storage. |
| `…​.fingerprint {--json}` | `laranail::license-verifier.fingerprint` | Device fingerprint + metadata. |
| `…​.ping {--fresh} {--json}` | `laranail::license-verifier.ping`, `laranail::license-verifier.ping` | Cached server reachability. |
| `…​.doctor {--json} {--strict}` | `laranail::license-verifier.doctor` | Config diagnostics (driver, keys, storage/fallback, sodium). |
| `…​.reminder {action} {--days=}` | `laranail::license-verifier.reminder` | `skip` / `clear` / `status`. |

## Storage, seats & maintenance

| Command | Alias | Notes |
|---|---|---|
| `…​.seats {action=list} {target?} {--json}` | `laranail::license-verifier.seats` | `list` / `revoke` seats (drivers supporting seat management). |
| `…​.token {action} {path?}` | `laranail::license-verifier.token` | `show` / `export` / `import` the offline token (air-gap). |
| `…​.keys {--json}` | `laranail::license-verifier.keys` | Show the stored PASETO public-key bundle. |
| `…​.clear {--force}` | `laranail::license-verifier.clear` | Wipe locally stored license data for this app. |
| `…​.watch {--cycles=} {--interval=}` | `laranail::license-verifier.watch` | Live status dashboard, refreshing on an interval. |

## Scripting

`--json` emits machine-readable output; combine with the exit code in CI:

```bash
if ! php artisan laranail::license-verifier.status --strict; then
  echo "License invalid — aborting." && exit 1
fi
```

---

[← Docs index](../../README.md#documentation)
