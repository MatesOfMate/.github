# Release Bump

Prepare and tag a new release for the MatesOfMate monorepo.

**Usage**: `/release-bump <version>` — e.g. `/release-bump 0.3.0`

## What This Skill Does

1. Validates that a version argument was provided
2. Commits any staged changes with an appropriate message
3. Verifies all `src/` packages have a CHANGELOG entry for the new version
4. Updates all `composer.json` files:
   - Stabilizes `matesofmate/common` constraint from `^<version>@dev` → `^<version>` in extension packages and demo
   - Sets `minimum-stability: dev` + `prefer-stable: true` in extension packages that depend on common
   - Bumps branch aliases from `<version>.x-dev` → `<next-minor>.x-dev` in all packages
5. Runs `composer update --lock` to sync lock files
6. Runs `composer lint && composer test` on every `src/` package to verify quality
7. Commits all changes with a release message
8. Creates an annotated git tag `v<version>`
9. Reminds you to push: `git push origin main && git push origin v<version>`

## Steps to Execute

### 1. Parse version from arguments

Extract `$ARGUMENTS` as the version string (e.g. `0.3.0`). If missing, ask the user.

Derive:
- `current_version` = the previous minor (e.g. `0.2` for a `0.3.0` release) — read from existing branch aliases in `src/*/composer.json`
- `next_minor` = `<major>.<minor+1>` (e.g. `0.4` for a `0.3.0` release)

### 2. Commit any staged changes

```bash
git status --short
```

If staged files exist, commit them:
```bash
git commit -m "Update <area> for <version> release

- <describe what was staged>"
```

### 3. Verify CHANGELOGs

Check each `src/*/CHANGELOG.md` (except `extension-template` — it stays at 0.1.0 as a clean starter):
- `src/common/CHANGELOG.md`
- `src/phpunit-extension/CHANGELOG.md`
- `src/phpstan-extension/CHANGELOG.md`
- `src/composer-extension/CHANGELOG.md`
- `src/rector-extension/CHANGELOG.md`

Each must have an entry for `<version>`. If any are missing, add them now and ask the user to confirm the bullet points, or draft them from recent git log:

```bash
git log --oneline <previous-tag>..HEAD -- src/<package>/
```

### 4. Update composer.json files

**Extension packages** (`src/phpunit-extension`, `src/phpstan-extension`, `src/composer-extension`, `src/rector-extension`):
- Change `"matesofmate/common": "^<current>@dev"` → `"matesofmate/common": "^<version_major.minor>"`
- Ensure `"minimum-stability": "dev"` and `"prefer-stable": true` are present
- Change `"dev-main": "<current>.x-dev"` → `"dev-main": "<next_minor>.x-dev"`

**Common package** (`src/common`):
- Change `"dev-main": "<current>.x-dev"` → `"dev-main": "<next_minor>.x-dev"`

**Extension template** (`src/extension-template`):
- Change `"dev-main": "<current>.x-dev"` → `"dev-main": "<next_minor>.x-dev"`

**Demo** (`demo/composer.json`):
- Change all `"matesofmate/*": "^<current>@dev"` → `"matesofmate/*": "^<version_major.minor>"`
- Ensure `"minimum-stability": "dev"` and `"prefer-stable": true` are present

### 5. Update lock files

```bash
for pkg in common phpunit-extension phpstan-extension composer-extension rector-extension extension-template; do
  cd src/$pkg && composer update --lock --quiet && cd ../..
done
```

### 6. Run quality checks

```bash
for pkg in common phpunit-extension phpstan-extension composer-extension rector-extension extension-template; do
  cd src/$pkg && composer lint && composer test && cd ../..
done
```

If any fail: fix the issue before proceeding.

### 7. Commit release changes

```bash
git add src/*/composer.json src/*/composer.lock src/*/CHANGELOG.md demo/composer.json src/common/rector.php
git commit -m "Release <version>

- Add <version> CHANGELOG entries
- Stabilize matesofmate/common constraint to ^<version_major.minor>
- Bump branch aliases to <next_minor>.x-dev for post-release development"
```

### 8. Create the tag

```bash
git tag -a v<version> -m "Release version <version>"
```

### 9. Remind user to push

Output:
```
Release v<version> is ready. To publish:

  git push origin main
  git push origin v<version>

The tag push will trigger .github/workflows/split.yml to publish all 7 sub-splits automatically.
```

## Notes

- `extension-template/CHANGELOG.md` intentionally stays at 0.1.0 — it's a clean template for new extension authors
- Deptrac currently has pre-existing violations from vendor directories being scanned; this does not block releases
- After pushing the tag, verify the GitHub Actions split workflow completes and all split repos receive the tag
- Packagist will auto-update once the split repos have the tag (if webhooks are configured)
