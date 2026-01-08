# CLAUDE.md - MatesOfMate Common Package

This file provides guidance to Claude Code when working with the MatesOfMate common package.

## Package Overview

The **common** package provides shared functionality for all MatesOfMate extensions. It follows **composition over inheritance** principles with minimal interfaces and concrete implementations.

**Location**: Separate package at monorepo root (`/Users/johannes/Development/matesofmate/common/`)

**Usage Pattern**: Extensions use the common package via Composer path repositories and composition.

## Architecture Principles

### Composition Over Inheritance
All common classes are designed for composition, not inheritance:
- Extensions create internal instances of common classes
- Configuration passed via constructor parameters
- No abstract base classes - only concrete implementations

### Minimal Interfaces
Interfaces expose only essential public methods:
- `ProcessExecutorInterface`: `execute()` only
- `ConfigurationDetectorInterface`: `detect()` only
- `MessageTruncatorInterface`: `truncate()` only

Helper methods (`findBinary()`, `buildCommand()`, `removeCommonPrefixes()`, etc.) are private implementation details.

### PHP Binary Reuse
The `ProcessExecutor` ensures PHP scripts run with the same PHP version as the current process:
- Uses `\PHP_BINARY` constant for PHP scripts
- `usePhpBinary` parameter distinguishes PHP scripts from system binaries
- Default `true` for PHP tools (phpunit, phpstan)
- Explicit `false` for system tools (git, composer)

## Directory Structure

```
common/
├── src/
│   ├── Config/              # Configuration file detection
│   │   ├── ConfigurationDetectorInterface.php
│   │   └── ConfigurationDetector.php
│   ├── Process/             # CLI process execution
│   │   ├── ProcessExecutorInterface.php
│   │   ├── ProcessExecutor.php
│   │   └── ProcessResult.php
│   └── Truncator/           # Token-efficient output
│       ├── MessageTruncatorInterface.php
│       └── MessageTruncator.php
├── tests/                   # PHPUnit tests mirroring src/
├── composer.json
├── phpunit.xml.dist
├── phpstan.dist.neon
├── rector.php
└── .php-cs-fixer.php
```

## Components

### Process Execution (`src/Process/`)

**ProcessExecutorInterface**
```php
interface ProcessExecutorInterface
{
    /**
     * @param array<int, string> $args
     */
    public function execute(
        string $binaryName,
        array $args = [],
        int $timeout = 300,
        bool $usePhpBinary = true
    ): ProcessResult;
}
```

**ProcessExecutor** - Concrete implementation
- Accepts `$vendorPaths` array in constructor for project-specific binary locations
- `findBinary()` is **private** - searches vendor paths, then system PATH
- `buildCommand()` is **private** - prepends PHP_BINARY when `$usePhpBinary` is true
- `execute()` is **public** - accepts binary name, automatically finds and executes

**ProcessResult** - Simple DTO
```php
class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {}

    public function isSuccessful(): bool
    {
        return 0 === $this->exitCode;
    }
}
```

**Critical: usePhpBinary Parameter**
```php
// PHP scripts - use PHP_BINARY (default)
$result = $executor->execute('phpunit', ['--version']);

// System binaries - skip PHP_BINARY (explicit false)
$result = $executor->execute('git', ['status'], usePhpBinary: false);
```

### Configuration Detection (`src/Config/`)

**ConfigurationDetectorInterface**
```php
interface ConfigurationDetectorInterface
{
    public function detect(?string $projectRoot = null): ?string;
}
```

**ConfigurationDetector** - Concrete implementation
- Accepts `$configFiles` array in constructor (e.g., `['phpunit.xml', 'phpunit.xml.dist']`)
- Searches for files in order specified
- Uses `getcwd()` fallback when `$projectRoot` is null
- Returns full path to first found file, or null

### Message Truncation (`src/Truncator/`)

**MessageTruncatorInterface**
```php
interface MessageTruncatorInterface
{
    public function truncate(string $message, int $maxLength = 200): string;
}
```

**MessageTruncator** - Concrete implementation
- Accepts `$prefixes` array in constructor for common prefixes to remove
- Shortens fully-qualified class names (e.g., `App\Very\Long\Namespace\ClassName` → `App\ClassName`)
- Truncates to max length with ellipsis if needed
- `removeCommonPrefixes()` and `shortenClassName()` are **private** implementation details

## Usage Patterns

### Process Executor Implementation

```php
use MatesOfMate\Common\Process\ProcessExecutor as CommonProcessExecutor;
use MatesOfMate\Common\Process\ProcessExecutorInterface;
use MatesOfMate\Common\Process\ProcessResult;

class PhpunitProcessExecutor implements ProcessExecutorInterface
{
    private readonly CommonProcessExecutor $executor;

    public function __construct()
    {
        $cwd = getcwd();
        $vendorPaths = false !== $cwd ? [
            $cwd.'/vendor/bin/phpunit',
            $cwd.'/vendor/phpunit/phpunit/phpunit',
        ] : [];

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

**Runner usage:**
```php
// PHPUnit (PHP script)
$result = $this->executor->execute('phpunit', ['--version']);

// Git (system binary)
$result = $this->executor->execute('git', ['status'], usePhpBinary: false);
```

### Configuration Detector Implementation

```php
use MatesOfMate\Common\Config\ConfigurationDetector as CommonConfigDetector;
use MatesOfMate\Common\Config\ConfigurationDetectorInterface;

class ConfigurationDetector implements ConfigurationDetectorInterface
{
    private readonly CommonConfigDetector $detector;

    public function __construct(private readonly string $projectRoot)
    {
        $this->detector = new CommonConfigDetector([
            'phpunit.xml',
            'phpunit.xml.dist',
            'phpunit.dist.xml',
        ]);
    }

    public function detect(?string $projectRoot = null): ?string
    {
        return $this->detector->detect($this->projectRoot);
    }

    // Extension-specific methods can be added
    public function getTestDirectories(): array
    {
        $configPath = $this->detect();
        // Parse XML and extract test directories
        return $directories ?: ['tests'];
    }
}
```

### Message Truncator Implementation

```php
use MatesOfMate\Common\Truncator\MessageTruncator as CommonMessageTruncator;
use MatesOfMate\Common\Truncator\MessageTruncatorInterface;

class MessageTruncator implements MessageTruncatorInterface
{
    private readonly CommonMessageTruncator $truncator;

    public function __construct()
    {
        $this->truncator = new CommonMessageTruncator([
            'Parameter ', 'Method ', 'Property ',
            'Call to ', 'Access to ',
        ]);
    }

    public function truncate(string $message, int $maxLength = 80): string
    {
        // Can add extension-specific logic before or after
        $message = $this->truncator->truncate($message, $maxLength);

        // Example: PHPStan-specific shortening
        $message = preg_replace('/of method [A-Za-z0-9\\\\]+::/', 'of ', $message);

        return $message;
    }
}
```

## Development Workflow

### Installing Dependencies
```bash
cd /Users/johannes/Development/matesofmate/common/
composer install
```

### Running Tests
```bash
composer test                          # Run all tests
composer test -- --coverage-html coverage/  # With coverage
vendor/bin/phpunit tests/Process/      # Specific directory
```

### Code Quality Checks
```bash
composer lint    # Runs all quality tools (validate, rector, php-cs-fixer, phpstan)
composer fix     # Auto-fixes code style and applies Rector refactorings
```

### Individual Tools
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff  # Check style
vendor/bin/php-cs-fixer fix                   # Apply fixes
vendor/bin/phpstan analyse                    # Static analysis (Level 8)
vendor/bin/rector process --dry-run           # Preview refactorings
vendor/bin/rector process                     # Apply refactorings
```

## Code Quality Standards

**PHP Version**: 8.2+ minimum (readonly properties, constructor property promotion)

**PHPStan Level**: 8 (maximum strictness)
- No `@phpstan-ignore` comments without justification
- All types explicit (parameters, return types, properties)

**PHP CS Fixer**: `@Symfony` ruleset with risky rules
- File header with MatesOfMate copyright
- Specific class element ordering (traits → constants → properties → methods)
- Parallel processing enabled

**Rector**: PHP 8.2 target
- UP_TO_PHP_82 rule set
- CODE_QUALITY, DEAD_CODE, EARLY_RETURN, TYPE_DECLARATION sets

**Testing**: PHPUnit 10.0+
- Tests mirror `src/` structure in `tests/`
- Descriptive test method names
- Test both success and failure paths

## Common Patterns to Follow

### Constructor Dependency Injection
```php
// ✅ Good - accepts configuration via constructor
public function __construct(
    private readonly array $vendorPaths = [],
) {}

// ❌ Bad - hardcoded or protected properties
protected array $vendorPaths = [];
```

### Private Implementation Details
```php
// ✅ Good - helper methods are private
private function findBinary(string $name): ?string {}
private function buildCommand(string $binaryPath): array {}

// ❌ Bad - exposing implementation details
public function findBinary(string $name): ?string {}
```

### Named Parameters for Booleans
```php
// ✅ Good - clear intent with named parameter
$result = $executor->execute('git', ['status'], usePhpBinary: false);

// ❌ Bad - unclear what false means
$result = $executor->execute('git', ['status'], 300, false);
```

### Empty Array Checks
```php
// ✅ Good - explicit empty array comparison
if ([] === $this->configFiles) {
    return null;
}

// ❌ Bad - empty() function
if (empty($this->configFiles)) {
    return null;
}
```

## Integration with Extensions

Extensions include the common package as a dependency in their `composer.json`.

**Service Registration:**
```php
// config/services.php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(PhpunitProcessExecutor::class);
    $services->set(ConfigurationDetector::class)
        ->arg('$projectRoot', '%kernel.project_dir%');
};
```

## Troubleshooting

### Autoloader Not Finding Classes
**Problem**: PHPStan reports "Class not found" after namespace changes

**Solution**:
1. Run `composer install` in extensions to update dependencies
2. Run `composer dump-autoload` in common package

### Git Commands Failing
**Problem**: Git commands prefixed with PHP_BINARY

**Solution**: Always use `usePhpBinary: false` for system binaries
```php
$result = $executor->execute('git', ['status'], usePhpBinary: false);
```

## Key Decisions and Rationale

### Why Composition Over Inheritance?
- **Flexibility**: Extensions can customize without modifying base classes
- **Testability**: Can mock interfaces in tests
- **Single Responsibility**: Common classes focus on core logic only
- **Explicit Dependencies**: Configuration visible in constructors

### Why Minimal Interfaces?
- **Clearer Contracts**: Only essential methods exposed
- **Easier Implementation**: Fewer requirements for implementers
- **Better Encapsulation**: Internal methods hidden from consumers

### Why usePhpBinary Parameter?
- **Correctness**: Git is not a PHP script, shouldn't use PHP_BINARY
- **Flexibility**: Single executor handles both PHP scripts and system binaries
- **Explicitness**: Named parameter makes intent clear at call site

## File Header Template

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

## DocBlock Annotations

**@author annotation**: All class-level DocBlocks should include an @author annotation with the current user:
```php
/**
 * Executes CLI tools with consistent PHP version.
 *
 * @author Your Name <your@email.com>
 */
class ProcessExecutor
{
}
```

**@internal annotation**: DO NOT use @internal in the common package classes or interfaces.

The common package provides a **public API for extension authors**:
```php
/**
 * Detects configuration files in project directories.
 *
 * @author Your Name <your@email.com>
 */
class ConfigurationDetector implements ConfigurationDetectorInterface
{
}
```

**Why NOT @internal:**
- These ARE public APIs for extension composition
- Extensions are expected to use these classes directly
- Interfaces define contracts between common and extensions
- Similar to Symfony Components - library APIs for developers

**Version Management:**
- Use semantic versioning for breaking changes
- Clear DocBlocks explain purpose and usage
- Treat breaking changes as major version bumps

## Commit Message Convention

**Format**:
```
Short summary (50 chars or less)

- Conceptual change description
- Another concept or improvement
- More changes as needed
```

**Rules**:
- ❌ NO AI attribution (no "Co-Authored-By: Claude", etc.)
- ✅ Short, descriptive summary line
- ✅ Bullet list describing concepts, not file names
- ✅ Focus on WHY and WHAT, not technical details

**Good Example**:
```
Simplify ProcessExecutor interface

- Make findBinary() and buildCommand() private
- Accept binary name instead of full command
- Add usePhpBinary parameter for system binaries
```

## Related Documentation

- **Monorepo CLAUDE.md**: `/Users/johannes/Development/matesofmate/CLAUDE.md`
- **Extension Template**: `/Users/johannes/Development/matesofmate/extension-template/`
- **PHPUnit Extension**: `/Users/johannes/Development/matesofmate/phpunit-extension/`
- **PHPStan Extension**: `/Users/johannes/Development/matesofmate/phpstan-extension/`
