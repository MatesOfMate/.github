# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This is the **MatesOfMate Monorepo** containing the entire MatesOfMate ecosystem - a collection of community-driven extensions for Symfony AI Mate that provide tools and resources to AI assistants through the Mate CLI.

**Key Projects:**
- `src/common/` - Shared functionality for all extensions
- `src/extension-template/` - Starter template for creating new extensions
- `src/composer-extension/` - Composer dependency management tools
- `src/phpunit-extension/` - PHPUnit testing tools with token-optimized output
- `src/phpstan-extension/` - PHPStan static analysis tools
- `awesome-mate/` - Curated resource list following Awesome List standards
- `demo/` - Demo Symfony application showcasing extensions
- `.github/` - Organization-wide configuration, workflows, and templates

## Monorepo Architecture

Following the **Symfony AI pattern**, this monorepo uses:

- **Centralized source**: All packages in `src/` directory
- **External testing**: `link` script symlinks packages to other projects
- **Subtree splitting**: Automated publishing to individual repos via GitHub Actions
- **Matrix CI/CD**: Dynamic package discovery and parallel testing

## Project Structure

```
matesofmate-monorepo/
├── .github/
│   ├── workflows/
│   │   ├── build-matrix.yml       # Dynamic package discovery
│   │   ├── tests.yml              # Matrix testing (PHP 8.2, 8.3)
│   │   ├── code-quality.yml       # PHPStan, CS-Fixer, Rector
│   │   └── split.yml              # Subtree splitting automation
│   └── scripts/
├── awesome-mate/                   # Curated resource list
│   └── README.md
├── demo/                           # Demo Symfony application
│   ├── config/
│   ├── src/
│   ├── tests/
│   └── composer.json              # Uses path repositories to src/
├── src/                            # All packages here (like Symfony AI)
│   ├── common/
│   │   ├── src/
│   │   ├── tests/
│   │   └── composer.json
│   ├── composer-extension/
│   ├── extension-template/
│   ├── phpunit-extension/
│   └── phpstan-extension/
├── link                            # Symlink helper (from Symfony AI)
├── config.subsplit-publish.json    # Subtree split config
├── composer.json                   # Root (minimal, dev-only)
├── CONTRIBUTING.md
└── README.md
```

## Common Package Architecture

The `common/` package provides shared functionality for all MatesOfMate extensions:

**Location**: `src/common/` in monorepo

**Components**:
- **Process**: ProcessExecutorInterface, ProcessExecutor, ProcessResult for CLI tool execution
- **Config**: ConfigurationDetectorInterface, ConfigurationDetector for auto-detecting config files
- **Truncator**: MessageTruncatorInterface, MessageTruncator for token-efficient output
- **DTO**: ProcessResult for command execution results

**Usage in Extensions**:
Extensions use composition with common classes:

```php
use MatesOfMate\Common\Process\ProcessExecutor as CommonProcessExecutor;
use MatesOfMate\Common\Process\ProcessExecutorInterface;

class PhpunitProcessExecutor implements ProcessExecutorInterface
{
    private readonly CommonProcessExecutor $executor;

    public function __construct()
    {
        $cwd = getcwd();
        $vendorPaths = false !== $cwd ? [$cwd.'/vendor/bin/phpunit'] : [];
        $this->executor = new CommonProcessExecutor($vendorPaths);
    }

    public function execute(
        string $binaryName,
        array $args = [],
        int $timeout = 300,
        bool $usePhpBinary = true
    ): ProcessResult {
        return $this->executor->execute($binaryName, $args, $timeout, $usePhpBinary);
    }
}
```

## Essential Commands

### Monorepo Development

```bash
# Install root dependencies
composer install

# Link monorepo packages to external project
./link /path/to/your-project

# Rollback symlinks
./link --rollback /path/to/your-project
```

### Working on Individual Packages

```bash
# Navigate to package
cd src/phpunit-extension

# Install dependencies
composer install

# Run all tests
composer test

# Check code quality
composer lint

# Auto-fix code style
composer fix
```

### Running Quality Tools

```bash
# PHP CS Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/php-cs-fixer fix

# PHPStan (level 8)
vendor/bin/phpstan analyse

# Rector (PHP 8.2)
vendor/bin/rector process --dry-run
vendor/bin/rector process

# PHPUnit
vendor/bin/phpunit
vendor/bin/phpunit tests/Capability/SpecificTest.php

# Deptrac (architecture validation at monorepo root)
composer deptrac
composer deptrac:clear  # Clear cache
```

## Development Workflow

### Working on Multiple Packages

```bash
cd src/phpunit-extension/
composer install
composer test && composer lint

cd ../phpstan-extension/
composer install
composer test && composer lint

# Update documentation
cd ../../awesome-mate/
# Edit README.md
```

### Testing Changes in Demo App

```bash
cd demo/
composer install  # Uses path repositories automatically
vendor/bin/mate discover
vendor/bin/phpunit
```

### Creating a New Extension

1. **Copy template**:
```bash
cp -r src/extension-template/ src/new-framework-extension/
cd src/new-framework-extension/
```

2. **Replace references**:
- Update `composer.json` name to `matesofmate/new-framework-extension`
- Replace all `example`/`Example`/`ExampleExtension` with your framework name
- Update `.github/CODEOWNERS`

3. **Implement capabilities**:
- Create tools in `src/Capability/` with `#[MateTool]` / `#[MateResource]`
- Register services in `config/config.php`
- Write tests in `tests/Capability/`

4. **Ensure quality**:
```bash
composer install
composer lint && composer test
```

## Code Quality Standards

### Organization-Wide Standards

All extensions must follow:

**PHP Requirements:**
- PHP 8.2+ minimum
- No `declare(strict_types=1)` by convention
- No final classes (extensibility)
- JSON encoding: `\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT`

**Code Quality Tools:**
- **PHPStan**: Level 8 (maximum strictness)
- **PHP CS Fixer**: `@Symfony` ruleset with risky rules
- **Rector**: UP_TO_PHP_82, code quality, dead code removal
- **PHPUnit**: Version 10.0+
- **Deptrac**: Architecture validation (monorepo-level)

**Required Composer Scripts:**
```json
{
    "scripts": {
        "test": "vendor/bin/phpunit",
        "lint": [
            "@composer validate --strict",
            "vendor/bin/rector process --dry-run",
            "vendor/bin/php-cs-fixer fix --dry-run --diff",
            "vendor/bin/phpstan analyse"
        ],
        "fix": [
            "vendor/bin/rector process",
            "vendor/bin/php-cs-fixer fix"
        ]
    }
}
```

### File Header Template

All PHP files must include:
```php
<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
```

### DocBlock Annotations

**@author annotation**: Required on all class-level DocBlocks
**@internal annotation**: Mark implementation details not for external use

## CI/CD Architecture

### Workflow Structure

**build-matrix.yml**: Discovers all packages in `src/` dynamically
**tests.yml**: Tests each package × PHP version (8.2, 8.3) independently
**code-quality.yml**: Runs lint on all packages in parallel
**split.yml**: Publishes packages to individual repos on tag push

### Workflow Pattern

1. Install package dependencies
2. Run package tests

### Testing Locally

```bash
# Simulate build-matrix discovery
find src -maxdepth 2 -name composer.json -type f | sed 's|/composer.json||'

# Test package builds
for pkg in src/*/; do
    cd "$pkg" && composer install && composer test && cd -
done
```

## Subtree Splitting

Packages are automatically split to individual repos on tag push:

**Configuration**: `config.subsplit-publish.json`
**Workflow**: `.github/workflows/split.yml`

**Split mapping**:
- `awesome-mate/` → `MatesOfMate/awesome-mate`
- `src/common/` → `MatesOfMate/common`
- `src/extension-template/` → `MatesOfMate/extension-template`
- `src/composer-extension/` → `MatesOfMate/composer-extension`
- `src/phpunit-extension/` → `MatesOfMate/phpunit-extension`
- `src/phpstan-extension/` → `MatesOfMate/phpstan-extension`

## Commit Message Convention

**Important**: Keep commit messages clean without AI attribution.

**Format**:
```
Short summary (50 chars or less)

- Conceptual change description
- Another concept or improvement
```

**✅ Good Examples**:
```
Add TOON formatter for token optimization

- Integrate helgesverre/toon library
- Reduce output tokens by 40-50%
- Preserve test result structure
```

**❌ Bad Examples**:
```
Update files

Co-Authored-By: Claude Code <noreply@anthropic.com>
```

**Rules**:
- ❌ NO AI attribution
- ✅ Short, descriptive summary line
- ✅ Bullet list describing concepts/improvements
- ✅ Focus on the WHY and WHAT

## Repository Relationships

### Monorepo Coordination

**src/extension-template/** → serves as base for all new extensions
**awesome-mate/** → catalogs and documents all published extensions
**demo/** → demonstrates extension usage with real Symfony app
**.github/** → provides organization-wide CI/CD and templates

When updating standards:
1. Update this `CLAUDE.md` for monorepo-wide changes
2. Update `src/extension-template/CLAUDE.md` for template changes
3. Update individual package `CLAUDE.md` files as needed
4. Document changes in `awesome-mate/` if user-facing

## Publishing Workflow

1. Ensure all quality checks pass: `composer lint && composer test`
2. Update CHANGELOG.md with version changes
3. Tag release: `git tag -a v0.1.0 -m "Release version 0.1.0"`
4. Push tag: `git push origin v0.1.0`
5. Split workflow automatically publishes to individual repos
6. Submit to Packagist (first time requires manual submission)
7. Update `awesome-mate/README.md` with new extension

## Local Development Tips

### Using link Script

Test monorepo packages in external projects:

```bash
# Link packages to external project
./link /path/to/external-project

# Work on monorepo, changes reflect immediately
cd src/phpunit-extension
# Make changes, test reflects in linked project

# Rollback when done
./link --rollback /path/to/external-project
```

## Extension Discovery Mechanism

All extensions use the `extra.ai-mate` section in `composer.json`:

```json
{
    "extra": {
        "ai-mate": {
            "scan-dirs": ["src/Capability"],
            "includes": ["config/config.php"],
            "instructions": "INSTRUCTIONS.md"
        }
    }
}
```

Mate scans `scan-dirs` by reflection for methods carrying `#[MateTool]`,
`#[MateResource]`, or `#[MateResourceTemplate]`, and loads `includes` into its
service container. Agents then reach the capabilities through the CLI:

```bash
vendor/bin/mate tools:list
vendor/bin/mate tools:inspect <tool>
vendor/bin/mate tools:call <tool> --<param>=<value>
vendor/bin/mate resources:read <uri>
```
