# TUI dashboard

`php artisan license:manage` (aliases `license`, `license:manage`) opens an interactive
license-management dashboard built on `laranail/console`.

## What it shows

A status card (driver · status · licensed-to · expiry · seats) followed by an action menu:

- **Activate** — prompts for the fields the active driver declares (`Driver::activationFields()`),
  so the form adapts per provider (e.g. PASETO asks for a key; Envato also asks for a buyer).
- **Validate** — runs the status check.
- **Refresh** — refreshes the token (drivers that support it).
- **Deactivate** — releases the activation after confirmation.
- **Drivers** — capability table.
- **Doctor** — diagnostics.
- **Quit**.

## Behaviour

- On a TTY the menu is keyboard-navigable; on a non-interactive shell (CI, pipes) the command
  degrades gracefully and prints `status` instead of prompting.
- Long operations (activation, download) are wrapped in a spinner.
- Signals (Ctrl-C) are handled by the console base.

## Relationship to the web UI

The TUI and every command call the same headless core (`DriverManager` / the `LicenseVerifier`
facade). The web GUI lives in the unified preset package (`laranail/license-verifier-ui`) and
calls the same core — so CLI, TUI and web stay consistent for any of the 14 drivers.

---

[← Docs index](../../README.md#documentation)
