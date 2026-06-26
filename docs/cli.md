# CLI reference

All commands are namespaced `laranail::license-verifier.*` with short `license:*` aliases.
They share the `laranail/console` services (`$this->services->interaction()`, `display()`),
support `--json` where noted, and return CI-friendly exit codes.

[← Docs index](../README.md#documentation)

## Exit codes

`0` valid / grace (or success) · `1` invalid / expired / unactivated · `2` server unreachable
(unreachable is `0` unless `--strict`) · `>2` usage/error.

## Lifecycle

| Command | Alias | Notes |
|---|---|---|
| `…​.activate {key?}` | `license:activate` | Activates; prints license table. |
| `…​.deactivate` | `license:deactivate` | Confirms, then releases the activation. |
| `…​.validate` | `license:validate` | Offline validity check; warns when expiring soon. |
| `…​.refresh` | `license:refresh` | Pulls a fresh token (drivers that support it). |
| `…​.info` | `license:info` | Full license detail table. |
| `…​.status {--strict} {--json}` | `license:status` | **CI gate** — exit code reflects validity. |

```bash
php artisan license:status --strict --json   # block a deploy when unlicensed
```

## Interactive

| Command | Alias | Notes |
|---|---|---|
| `…​.manage` | `license`, `license:manage` | TUI dashboard: status panel + action menu. Non-TTY → `status`. |

## Drivers, source & diagnostics

| Command | Alias | Notes |
|---|---|---|
| `…​.drivers {--json}` | `license:drivers` | Capability table of all drivers (active marked). |
| `…​.driver {name?} {--test} {--json}` | `license:driver` | Inspect one driver (capabilities; `--test` pings health). |
| `…​.source {--json}` | `license:source` | Show the configured license-detail source + storage. |
| `…​.fingerprint {--json}` | `license:fingerprint` | Device fingerprint + metadata. |
| `…​.ping {--fresh} {--json}` | `license:ping`, `license:check-connection` | Cached server reachability. |
| `…​.doctor {--json} {--strict}` | `license:doctor` | Config diagnostics (driver, keys, storage/fallback, sodium). |
| `…​.reminder {action} {--days=}` | `license:reminder` | `skip` / `clear` / `status`. |

## Storage, seats & maintenance

| Command | Alias | Notes |
|---|---|---|
| `…​.seats {action=list} {target?} {--json}` | `license:seats` | `list` / `revoke` seats (drivers supporting seat management). |
| `…​.token {action} {path?}` | `license:token` | `show` / `export` / `import` the offline token (air-gap). |
| `…​.keys {--json}` | `license:keys` | Show the stored PASETO public-key bundle. |
| `…​.clear {--force}` | `license:clear` | Wipe locally stored license data for this app. |
| `…​.watch {--cycles=} {--interval=}` | `license:watch` | Live status dashboard, refreshing on an interval. |

## Scripting

`--json` emits machine-readable output; combine with the exit code in CI:

```bash
if ! php artisan license:status --strict; then
  echo "License invalid — aborting." && exit 1
fi
```

[← Docs index](../README.md#documentation)
