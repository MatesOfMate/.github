# AGENTS.md

Guidelines for agents working on the Rector extension.

## Focus

Maintain a safe, project-aware Mate extension for Rector workflows. Keep package docs, output descriptions, and troubleshooting guidance aligned with the actual implementation.

## Important Rules

- Register capabilities in `config/config.php`.
- Keep `rector-preview` read-only and always dry-run.
- Keep `rector-apply` as the dedicated write-capable Rector tool.
- Do not install Rector, generate `rector.php`, edit Rector configuration, run tests, stage files, or commit from extension tools.
- This package uses Mate's core `ResponseEncoder` for tool payloads.
- Describe TOON as optional runtime behavior provided by Mate, with JSON fallback.

## When Updating Behavior

1. update capability, discovery, runner, parser, formatter, or validation code
2. update tests
3. update README and `INSTRUCTIONS.md`
4. run `composer test` and `composer lint`

## Commit Messages

Never include AI attribution. Focus on the conceptual change and user-facing outcome.
