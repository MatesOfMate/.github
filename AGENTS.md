# AGENTS.md - MatesOfMate Common Package

AI Agent reference for the MatesOfMate common package. This document provides structured information for AI assistants working with shared extension functionality.

## Package Purpose

The common package provides reusable components for MatesOfMate extensions following **composition over inheritance** principles. Extensions create internal instances of common classes rather than extending them.

**Package Name**: `matesofmate/common`
**Namespace**: `MatesOfMate\Common`
**PHP Version**: 8.2+ (uses readonly properties)

## Component Overview

| Component | Purpose | Interface | Implementation |
|-----------|---------|-----------|----------------|
| Process Executor | CLI tool execution with PHP binary reuse | `ProcessExecutorInterface` | `ProcessExecutor` |
| Configuration Detector | Config file auto-detection | `ConfigurationDetectorInterface` | `ConfigurationDetector` |
| Message Truncator | Token-efficient output | `MessageTruncatorInterface` | `MessageTruncator` |

## Process Execution Components

### ProcessExecutorInterface

**Location**: `src/Process/ProcessExecutorInterface.php`
**Purpose**: Execute CLI tools with consistent PHP version

**Method**:
```php
public function execute(
    string $binaryName,
    array $args = [],
    int $timeout = 300,
    bool $usePhpBinary = true
): ProcessResult;
```

**Parameters**:
- `$binaryName` - Binary to execute (e.g., `'phpunit'`, `'git'`)
- `$args` - Command arguments as array
- `$timeout` - Maximum execution time in seconds (default: 300)
- `$usePhpBinary` - Whether to prefix with PHP_BINARY (default: true)

**Returns**: `ProcessResult` with exitCode, output, errorOutput

**Key Insight**: Set `usePhpBinary: false` for system binaries (git, composer), keep default `true` for PHP scripts (phpunit, phpstan).

### ProcessExecutor

**Location**: `src/Process/ProcessExecutor.php`
**Purpose**: Concrete process executor with vendor path support

**Constructor**:
```php
public function __construct(
    private readonly array $vendorPaths = []
) {}
```

**Behavior**:
1. Searches `$vendorPaths` array for binary (project-specific locations)
2. Falls back to system PATH via `ExecutableFinder`
3. Conditionally prepends `\PHP_BINARY` based on `$usePhpBinary` parameter
4. Executes via Symfony Process component
5. Returns structured `ProcessResult`

**Private Methods** (implementation details):
- `findBinary(string $name): ?string` - Binary location resolution
- `buildCommand(string $binaryPath): array` - Command array construction with PHP_BINARY

**Usage Pattern**:
```php
$executor = new ProcessExecutor([
    '/path/to/vendor/bin/phpunit',
    '/path/to/vendor/phpunit/phpunit/phpunit',
]);

// PHP script - uses PHP_BINARY
$result = $executor->execute('phpunit', ['--version']);

// System binary - skips PHP_BINARY
$result = $executor->execute('git', ['status'], usePhpBinary: false);
```

### ProcessResult

**Location**: `src/Process/ProcessResult.php`
**Purpose**: DTO for process execution results

**Properties**:
- `int $exitCode` - Process exit code (0 = success)
- `string $output` - Standard output content
- `string $errorOutput` - Standard error content

**Methods**:
- `isSuccessful(): bool` - Returns true if exitCode is 0

**Example**:
```php
$result = $executor->execute('phpunit', ['--version']);

if ($result->isSuccessful()) {
    echo $result->output;  // PHPUnit version output
} else {
    throw new \RuntimeException($result->errorOutput);
}
```

## Configuration Detection Components

### ConfigurationDetectorInterface

**Location**: `src/Config/ConfigurationDetectorInterface.php`
**Purpose**: Minimal contract for config file detection

**Method**:
```php
public function detect(?string $projectRoot = null): ?string;
```

**Parameters**:
- `$projectRoot` - Project root directory (optional, uses `getcwd()` if null)

**Returns**: Full path to config file, or null if not found

### ConfigurationDetector

**Location**: `src/Config/ConfigurationDetector.php`
**Purpose**: Concrete config file detector with prioritized search

**Constructor**:
```php
public function __construct(
    private readonly array $configFiles = []
) {}
```

**Behavior**:
1. Accepts ordered array of config filenames (e.g., `['phpunit.xml', 'phpunit.xml.dist']`)
2. Uses provided `$projectRoot` or falls back to `getcwd()`
3. Returns first found file in search order
4. Returns null if no files found or configFiles is empty

**Private Method**:
- `exists(string $configFile): bool` - File existence check

**Usage Pattern**:
```php
$detector = new ConfigurationDetector([
    'phpunit.xml',
    'phpunit.xml.dist',
    'phpunit.dist.xml',
]);

$configPath = $detector->detect('/path/to/project');

if ($configPath) {
    echo "Found config: $configPath";
} else {
    echo "No configuration file found";
}
```

**Extension Pattern**:
```php
class PhpunitConfigurationDetector implements ConfigurationDetectorInterface
{
    private readonly CommonConfigDetector $detector;

    public function __construct(private readonly string $projectRoot)
    {
        $this->detector = new CommonConfigDetector([
            'phpunit.xml',
            'phpunit.xml.dist',
        ]);
    }

    public function detect(?string $projectRoot = null): ?string
    {
        return $this->detector->detect($this->projectRoot);
    }

    // Extension-specific method
    public function getTestDirectories(): array
    {
        $configPath = $this->detect();
        // Parse XML and extract directories
        return $directories;
    }
}
```

## Message Truncation Components

### MessageTruncatorInterface

**Location**: `src/Truncator/MessageTruncatorInterface.php`
**Purpose**: Minimal contract for message truncation

**Method**:
```php
public function truncate(string $message, int $maxLength = 200): string;
```

**Parameters**:
- `$message` - Message to truncate
- `$maxLength` - Maximum length (default: 200)

**Returns**: Truncated message with ellipsis if needed

### MessageTruncator

**Location**: `src/Truncator/MessageTruncator.php`
**Purpose**: Token-efficient output with prefix removal and class name shortening

**Constructor**:
```php
public function __construct(
    private readonly array $prefixes = []
) {}
```

**Behavior**:
1. Removes common prefixes from message start (e.g., `'Parameter '`, `'Method '`)
2. Shortens fully-qualified class names (`App\Very\Long\Namespace\ClassName` → `App\ClassName`)
3. Truncates to max length with `'...'` suffix if still too long

**Public Methods**:
- `truncate(string $message, int $maxLength = 200): string` - Main truncation logic

**Private Methods** (implementation details):
- `removeCommonPrefixes(string $message, array $prefixes): string`
- `shortenClassName(string $text): string`

**Usage Pattern**:
```php
$truncator = new MessageTruncator([
    'Parameter ',
    'Method ',
    'Property ',
    'Call to ',
]);

$long = 'Parameter #1 $user of method App\Very\Long\Namespace\UserService::createUser() expects User, null given.';
$short = $truncator->truncate($long, 80);
// Result: "#1 $user of App\UserService::createUser() expects User, null given."
```

**Extension Pattern**:
```php
class PhpstanMessageTruncator implements MessageTruncatorInterface
{
    private readonly CommonMessageTruncator $truncator;

    public function __construct()
    {
        $this->truncator = new CommonMessageTruncator([
            'Parameter ', 'Method ', 'Property ',
        ]);
    }

    public function truncate(string $message, int $maxLength = 80): string
    {
        $message = $this->truncator->truncate($message, $maxLength);

        // PHPStan-specific: shorten method references
        $message = preg_replace('/of method [A-Za-z0-9\\\\]+::/', 'of ', $message);

        return $message;
    }
}
```

## Integration Patterns

### Composition Pattern

All common classes are designed for composition, not inheritance:

```php
// ✅ Correct - Composition
class ExtensionClass implements CommonInterface
{
    private readonly CommonClass $common;

    public function __construct()
    {
        $this->common = new CommonClass($config);
    }

    public function method(): Result
    {
        return $this->common->method();
    }
}

// ❌ Incorrect - Inheritance
class ExtensionClass extends CommonClass
{
    // Don't do this
}
```

### Service Registration

Extensions should register their wrappers in Symfony DI:

```php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(PhpunitProcessExecutor::class);

    $services->set(ConfigurationDetector::class)
        ->arg('$projectRoot', '%kernel.project_dir%');

    $services->set(MessageTruncator::class);
};
```

### Dependency Management

Extensions include the common package as a dependency in their `composer.json` file.

## Critical Usage Rules

### Rule 1: PHP_BINARY Usage

**PHP Scripts** (phpunit, phpstan, rector, php-cs-fixer):
```php
// ✅ Correct - uses PHP_BINARY (default)
$result = $executor->execute('phpunit', ['--version']);
$result = $executor->execute('phpstan', ['analyse']);
```

**System Binaries** (git, composer, npm):
```php
// ✅ Correct - skips PHP_BINARY (explicit false)
$result = $executor->execute('git', ['status'], usePhpBinary: false);
$result = $executor->execute('composer', ['show'], usePhpBinary: false);
```

**Why?** Git commands should not be executed as `php /usr/bin/git`, only PHP scripts should be prefixed with PHP_BINARY to ensure version consistency.

### Rule 2: Interface Implementation

Only implement interfaces, never extend common classes:

```php
// ✅ Correct
class MyExecutor implements ProcessExecutorInterface {
    private readonly ProcessExecutor $executor;
}

// ❌ Incorrect
class MyExecutor extends ProcessExecutor {
}
```

### Rule 3: Empty Array Checks

Use explicit array comparison, not `empty()`:

```php
// ✅ Correct
if ([] === $this->configFiles) {
    return null;
}

// ❌ Incorrect (Rector will flag this)
if (empty($this->configFiles)) {
    return null;
}
```

### Rule 4: Named Parameters for Booleans

Always use named parameters for boolean flags:

```php
// ✅ Correct - clear intent
$result = $executor->execute('git', ['status'], usePhpBinary: false);

// ❌ Incorrect - unclear meaning
$result = $executor->execute('git', ['status'], 300, false);
```

## Code Quality Tools

### PHPStan (Level 8)
```bash
vendor/bin/phpstan analyse
```
- Maximum strictness
- All types must be explicit
- No suppression without justification

### PHP CS Fixer (@Symfony)
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff  # Check
vendor/bin/php-cs-fixer fix                   # Apply
```
- File header required on all PHP files
- Specific class element ordering
- Parallel processing enabled

### Rector (PHP 8.2)
```bash
vendor/bin/rector process --dry-run  # Preview
vendor/bin/rector process            # Apply
```
- UP_TO_PHP_82 rule set
- CODE_QUALITY, DEAD_CODE, EARLY_RETURN, TYPE_DECLARATION

### PHPUnit (10.0+)
```bash
vendor/bin/phpunit
vendor/bin/phpunit --coverage-html coverage/
```
- Tests mirror `src/` structure
- Test both success and failure paths

## Common Issues and Solutions

### Issue 1: Class Not Found After Namespace Change

**Symptom**: PHPStan reports "Class MatesOfMate\Common\Formatter\MessageTruncator not found"

**Solution**:
1. Check correct namespace in imports: `use MatesOfMate\Common\Truncator\MessageTruncator;`
2. Run `composer install` in extensions to update dependencies
3. Run `composer dump-autoload` in common package

### Issue 2: Git Commands Failing

**Symptom**: Git commands return errors or execute incorrectly

**Solution**: Always use `usePhpBinary: false` for git:
```php
$result = $executor->execute('git', ['status'], usePhpBinary: false);
```

### Issue 3: ProcessExecutor Methods Not Found

**Symptom**: "Call to undefined method findBinary()"

**Solution**: These methods are now private. Use `execute()` with binary name instead:
```php
// ❌ Old way (no longer works)
$binary = $executor->findBinary('phpunit');
$command = $executor->buildCommand($binary);
$result = $executor->execute($command);

// ✅ New way
$result = $executor->execute('phpunit', ['--version']);
```

## Design Rationale

### Why Composition Over Inheritance?

**Benefits**:
- **Flexibility**: Extensions customize without modifying base classes
- **Testability**: Can mock interfaces in tests
- **Single Responsibility**: Common classes focus on core logic only
- **Explicit Dependencies**: Configuration visible in constructors
- **No Diamond Problem**: Avoid multiple inheritance conflicts
- **Easier Evolution**: Change implementation without breaking extensions

### Why Minimal Interfaces?

**Benefits**:
- **Clearer Contracts**: Only essential methods exposed
- **Easier Implementation**: Fewer requirements for implementers
- **Better Encapsulation**: Internal methods hidden from consumers
- **Simpler Testing**: Less surface area to mock
- **Future-Proof**: Can add private methods without breaking changes

### Why usePhpBinary Parameter?

**Benefits**:
- **Correctness**: Git and system binaries don't need PHP_BINARY prefix
- **Flexibility**: Single executor handles both PHP scripts and system binaries
- **Explicitness**: Named parameter makes intent clear at call site
- **Safety**: Default `true` ensures PHP version consistency for PHP scripts

## Example: Complete Extension Implementation

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

namespace MatesOfMate\PHPUnitExtension\Process;

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

## Related Documentation

- **Common CLAUDE.md**: `/Users/johannes/Development/matesofmate/common/CLAUDE.md`
- **Common README.md**: `/Users/johannes/Development/matesofmate/common/README.md`
- **Monorepo CLAUDE.md**: `/Users/johannes/Development/matesofmate/CLAUDE.md`
- **Extension Template**: `/Users/johannes/Development/matesofmate/extension-template/`
