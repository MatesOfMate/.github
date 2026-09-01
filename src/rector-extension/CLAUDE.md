# CLAUDE.md

Guidance for working on the Rector extension.

## Overview

This package provides Rector inspection, dry-run preview, and write-capable apply tools for Symfony Mate using Mate's core response encoder.

## Current Mate Workflow

- initialize projects with `vendor/bin/mate init`
- current Mate setups auto-discover extensions after install and update
- `vendor/bin/mate discover` refreshes discovery and generated instruction artifacts
- run tools from the CLI with `vendor/bin/mate tools:call <tool> --<param>=<value>`
- use `vendor/bin/mate debug:extensions` and `vendor/bin/mate debug:capabilities` to troubleshoot loading problems

## Structure

- `src/Capability/` contains tools
- `src/Workflow/` holds the shared preview/apply pipeline both write tools delegate to
- `src/Discovery/` inspects project-local Rector setup
- `src/Validation/` validates project-root path boundaries
- `src/Runner/` builds and runs Rector process commands
- `src/Parser/` parses Rector JSON output and preserves raw fallback output
- `src/Formatter/` emits encoded tool output
- `config/config.php` registers services

## Safety Model

- `rector-inspect` never executes Rector.
- `rector-preview` always adds `--dry-run`.
- `rector-apply` never adds `--dry-run` and writes Rector changes.
- Paths must stay inside `%mate.root_dir%`.
- Container execution is only supported through explicit `matesofmate_rector.custom_command`.

## Commands

```bash
composer install
composer test
composer lint
composer fix
vendor/bin/mate tools:list --extension=matesofmate/rector-extension
```

## Standards

- no `declare(strict_types=1)` by project convention
- non-final classes by project convention
- docs must match actual output modes and tool names
