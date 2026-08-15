# Rector Extension for Symfony AI Mate

Rector refactoring tools for AI assistants. This package inspects project-local Rector setup, previews changes in dry-run mode, and applies refactors through a dedicated write-capable tool.

## Features

- inspect project-local Rector installation, configuration, and Composer scripts
- preview Rector changes with `--dry-run` enforced
- encoded output with three consistent detail modes
- dedicated write-capable apply workflow
- custom command support for Docker or wrapper-based setups

## Installation

```bash
composer require --dev matesofmate/rector-extension
vendor/bin/mate init
```

In current AI Mate setups, extension discovery is handled automatically after Composer install and update. Run `vendor/bin/mate discover` when you want to refresh discovery artifacts such as `mate/AGENT_INSTRUCTIONS.md`.

Useful Mate commands:

```bash
vendor/bin/mate debug:extensions
vendor/bin/mate debug:capabilities
vendor/bin/mate mcp:tools:list --extension=matesofmate/rector-extension
```

Use the generated wrapper for Codex:

```bash
./bin/codex
```

## Custom Command Configuration

If Rector must run through Docker or another wrapper command, configure `matesofmate_rector.custom_command`.

```php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()->set('matesofmate_rector.custom_command', [
        'docker', 'compose', 'exec', 'php', 'vendor/bin/rector',
    ]);
};
```

## Requirements

- PHP 8.2+
- Symfony AI Mate 0.11+ required
- Rector installed in the target project, or `matesofmate_rector.custom_command` configured
- A Rector configuration such as `rector.php`

This extension does not install Rector, generate `rector.php`, or modify Rector configuration.

## Available Tools

- `rector-inspect`
- `rector-preview`
- `rector-apply`

All tools return encoded strings through Mate's core `ResponseEncoder`. Install the suggested `helgesverre/toon` package if you want TOON responses; otherwise the same payload falls back to JSON.

## Output Modes

- `default`
- `summary`
- `detailed`

Every response reports a `status`. Rector uses a dedicated exit code to signal that it found
code to change, so a preview that reports pending changes is a `SUCCESS` with a non-zero
`changed_file_count`. `FAILED` means Rector itself could not process the code — parse errors
and similar problems are listed under `errors` together with `error_count`.

## Safe Workflow

1. Run `rector-inspect` to confirm the project has Rector and a configuration.
2. Run `rector-preview` to execute Rector with `--dry-run`.
3. Review changed files, rule names, diffs, output, and diagnostics.
4. Run `rector-apply` when Rector changes should be written.

`rector-preview` always adds `--dry-run` and never writes project source files. `rector-apply` never adds `--dry-run`; it writes Rector changes and does not run tests, commit changes, stage files, or invoke Rector again afterward.

## Development

```bash
composer install
composer test
composer lint
composer fix
```

## License

MIT
