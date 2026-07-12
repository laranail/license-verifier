# Release

Releases are **tag-driven** (`vX.Y.Z`) and published on GitHub; Packagist syncs from the tag.

## Steps

1. Update `CHANGELOG.md`: move the unreleased entries under a new `## [X.Y.Z] - YYYY-MM-DD` heading.
2. Commit on `main` (ensure `git config user.email` is set to your GitHub no-reply address).
3. Tag + push:
   ```bash
   git tag vX.Y.Z
   git push origin main --tags
   ```
4. Create the GitHub release with that version's `CHANGELOG.md` section as the body.

CI (`tests.yml` on PHP 8.4/8.5 + `static-analysis.yml`: pint, phpstan, rector) must be green on
the tagged commit.

## Versioning

Semver, currently pre-1.0 (`0.x`) — the public API may change between minor versions. Breaking
changes to the public API (the `LicenseVerifier` facade, the `Contracts\*` interfaces including
the driver capability interfaces, the config schema, command signatures) are documented in
[UPGRADE.md](../UPGRADE.md).

---

[← Docs index](../README.md#documentation)
