# Contributing

Thanks for your interest in contributing to `laranail/license-verifier`.

## Getting started

```bash
git clone https://github.com/laranail/license-verifier
cd license-verifier
composer install
composer test
```

## Conventions

- PHP `^8.4 || ^8.5`, Laravel `^13`.
- `declare(strict_types=1);` in every file; `final` classes where applicable;
  `#[Override]` on inherited methods; explicit return/param types; early returns;
  curly braces on all control flow.
- Drivers live behind `Contracts/Driver` + capability sub-interfaces and are
  resolved by `DriverManager`. New providers add a driver, not special-casing.
- Artisan commands follow the laranail shape `laranail::license-verifier.<command>`
  (with a `license:*` alias) and extend the base `Commands\Command`.
- Prefer PHPDoc over inline comments; add array-shape PHPDoc where useful.
- Never hit real provider APIs in tests — mock with `Http::fake()`.

## Quality gates

```bash
composer test       # Pest (Unit + Feature)
composer analyse    # PHPStan (level 5)
composer format     # Pint
composer rector     # Rector (dry-run)
```

All must be green. New behaviour needs a Pest test that proves it.

## Pull requests

- Subject ≤ 72 chars, imperative mood; the body explains *why*.
- Update `CHANGELOG.md` under `Unreleased` and `docs/AUDIT.md` where relevant.
- No AI-assistant attribution in commits or PRs.

## Credits

`laranail/license-verifier` is developed and maintained by Simtabi LLC.
