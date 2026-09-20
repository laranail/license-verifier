# Changelog

All notable changes to `laranail/license-verifier` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **`laranail::license-verifier.watch --cycles=` looped forever**, including in a non-interactive
  run. `--cycles=` and `--cycles=abc` both reach `(int)` and become `0`; the loop breaks on
  `++$count === $cycles` with `$count` starting at 1, so `0` never matches. The `null` guard above
  it that returns early in a non-interactive run did not fire either, because `0` is not `null` --
  so a CI invocation with a mistyped value hung instead of printing one snapshot and exiting.

- **`laranail::license-verifier.reminder skip --days=` skipped for zero days**, i.e. did not skip.
  `ReminderManager::skip()` falls back to `reminder.default_skip_days` on `null`, and `(int) ''` is
  `0`, which is not `null` -- so the fallback was bypassed and the reminder was written as already
  expired. A mistyped `--days=3x` did the same.

  Both were the same defect: the guard in front of each cast tested for `null`, which is what an
  **absent** option returns, while an option written without a value arrives as `''`. Both now use
  `intOption()` from `laranail/package-tools`, which reports empty and non-numeric alike as absent.

- **Five commands ignored the configured licence key when the argument was empty.**
  `activate`, `deactivate`, `info`, `refresh` and `validate` each resolved the key as
  `$this->argument('key') ?? config('license-verifier.license_key')`. `??` substitutes for `null`,
  which is what an OMITTED argument gives; `artisan … ""` gives `''`, which is not null, so the
  empty string won and the command ran against no key at all. They now use `strArgOrNull()`.

### Changed

- The base `Commands\Command` applies `laranail/package-tools`' `Commands\Concerns\ReadsOptions`,
  so every command in this package has the normalising option accessors.

### Added

- **`assertNoNullOnlyOptionGuards()` is enforced over `src/`**, with no exemptions, so the shape
  that produced all three defects above cannot return unnoticed.

## [Unreleased]

### Changed

- The PHP floor is `^8.4.1`, up from `^8.4`. `laranail/package-tools` and `laranail/console`
  are `^8.4.1`, so a resolver that took the manifest at its word and pinned the platform to
  8.4.0 could not install them. Dependabot does exactly that, and had been failing on it.

## [0.1.0] - 2026-07-11

Initial public release.
