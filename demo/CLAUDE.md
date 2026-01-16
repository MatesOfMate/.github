# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Demo Application** for the MatesOfMate ecosystem - a Symfony 8.0 application that showcases MCP (Model Context Protocol) extensions for AI assistants. The project contains intentional errors (failing tests, PHPStan errors) to demonstrate extension functionality.

## IMPORTANT: Use MCP Tools First

**ALWAYS use the MCP tools instead of running CLI commands directly.** The MCP server provides token-optimized output specifically designed for AI assistants.

Instead of:
- `bin/phpunit` → Use `phpunit-run-suite` MCP tool
- `bin/phpstan` → Use `phpstan-analyse` MCP tool
- `composer install` → Use `composer-install` MCP tool
- `composer update` → Use `composer-update` MCP tool
- `composer require` → Use `composer-require` MCP tool

The MCP tools return TOON (Token-Optimized Object Notation) format which reduces token usage by 40-50% while preserving all essential information.

## MCP Server Commands

```bash
vendor/bin/mate discover                 # Discover extensions
vendor/bin/mate serve                    # Start MCP server
vendor/bin/mate mcp:tools:list           # List available tools
vendor/bin/mate mcp:tools:inspect <name> # Inspect specific tool
vendor/bin/mate debug:capabilities       # Show all capabilities by extension
```

## MCP Tools Reference

### PHPUnit Extension (`matesofmate/phpunit-extension`)

| Tool | Description | Required Params |
|------|-------------|-----------------|
| `phpunit-run-suite` | Run full test suite with TOON output | `mode`: default\|summary\|detailed\|by-file\|by-class |
| `phpunit-run-file` | Run tests from specific file | `file` (path), `mode` |
| `phpunit-run-method` | Run single test method | `class`, `method`, `mode` |
| `phpunit-list-tests` | List available tests | `directory` (optional) |

### PHPStan Extension (`matesofmate/phpstan-extension`)

| Tool | Description | Required Params |
|------|-------------|-----------------|
| `phpstan-analyse` | Run static analysis | `mode`: toon\|summary\|detailed\|by-file\|by-type, `level` (0-9) |
| `phpstan-analyse-file` | Analyze specific file | `file` (path), `mode`, `level` |
| `phpstan-clear-cache` | Clear result cache | `configuration` (optional) |

**Resource**: `phpstan://config` - Returns project configuration details

### Composer Extension (`matesofmate/composer-extension`)

| Tool | Description | Required Params |
|------|-------------|-----------------|
| `composer-install` | Install dependencies | `preferDist`, `noDev`, `optimizeAutoloader` |
| `composer-update` | Update dependencies | `packages` (optional), `withDependencies` |
| `composer-require` | Add package | `package`, `version` (optional), `dev` |
| `composer-remove` | Remove package | `package`, `dev` |
| `composer-why` | Show dependents | `package` |
| `composer-why-not` | Diagnose version conflicts | `package`, `version` (optional) |

### Symfony AI Mate (`symfony/ai-mate`)

| Tool | Description |
|------|-------------|
| `php-version` | Get PHP version |
| `operating-system` | Get current OS |
| `operating-system-family` | Get OS family |
| `php-extensions` | List PHP extensions |

### Monolog Extension (`symfony/ai-monolog-mate-extension`)

| Tool | Description | Required Params |
|------|-------------|-----------------|
| `monolog-search` | Search logs by term | `term`, `level`, `channel`, `limit` |
| `monolog-search-regex` | Search with regex | `pattern` |
| `monolog-context-search` | Search by context field | `field`, `value` |
| `monolog-tail` | Get last N entries | `count` |
| `monolog-list-files` | List log files | `environment` (optional) |
| `monolog-list-channels` | List log channels | - |
| `monolog-by-level` | Filter by level | `level` |

### Symfony Extension (`symfony/ai-symfony-mate-extension`)

| Tool | Description |
|------|-------------|
| `symfony-services` | List all Symfony services |

## Architecture

### Extension Configuration

Extensions are enabled via `mate/extensions.php`:
```php
return [
    'matesofmate/composer-extension' => ['enabled' => true],
    'matesofmate/phpstan-extension' => ['enabled' => true],
    'matesofmate/phpunit-extension' => ['enabled' => true],
    'symfony/ai-mate' => ['enabled' => true],
    'symfony/ai-monolog-mate-extension' => ['enabled' => true],
    'symfony/ai-symfony-mate-extension' => ['enabled' => true],
];
```

### MCP Server Configuration

The `mcp.json` (symlinked as `.mcp.json`) configures the MCP server:
```json
{
    "mcpServers": {
        "symfony-ai-mate": {
            "command": "./vendor/bin/mate",
            "args": ["serve", "--force-keep-alive"]
        }
    }
}
```

### Custom MCP Tools

Custom tools go in `mate/src/` and register via `mate/config.php`. The `extra.ai-mate.scan-dirs` in `composer.json` points to `mate/src` for discovery.

## Test Fixtures

The demo includes intentional test scenarios:

**CalculatorTest.php**:
- Passing: `testAdd`, `testSubtract`, `testMultiply`, `testDivide`, `testPower`, `testDivideByZero`
- Failing: `testAddFailing` (wrong expected value)
- Erroring: `testDivideErroring` (unhandled exception), `testPhpstanErrors` (undefined methods)

**NonExistentClassTest.php**: Errors due to missing `App\NonExistentClass`

**SkippedTest.php**: `testSkippedDueToPHPVersion` skipped via `@requires PHP >= 9.0`

## PHPStan Fixtures

`src/Calculator.php` contains intentional errors:
- `problematicMethod()`: Undefined variable, unreachable code, missing return type
- `typeIssueMethod()`: Returns string instead of int
- `unusedParamMethod()`: Unused parameter
