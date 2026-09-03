---
name: rector-refactoring
description: Run Rector through Mate, inspecting the setup, previewing the refactors, then applying them. Use when automated refactoring, a PHP version upgrade, or a rule-driven modernization is asked for, and before touching a project whose Rector setup you do not know yet. Not for static analysis findings (phpstan static analysis) or running tests (phpunit test runs).
---

# Rector refactoring

Runs Rector through Mate's CLI with `--output-format=json`. Three tools, meant to be used in this order:

- `rector-inspect` (no parameters): whether Rector is installed (`rector_installed`, `local_binary`), which config was found (`configuration`), which Composer scripts mention it, the `preferred_strategy` it would run with, and `diagnostics` naming what is missing. Runs nothing.
- `rector-preview` (opt `path`, `configuration`, `debug`, `rulesSummary`, `mode`): the same run with `--dry-run`. Never writes.
- `rector-apply` (same parameters): writes the changes to disk.

These commands accept `--format`: `json` to parse the result, `toon` (when `helgesverre/toon` is installed) for the smallest context footprint.

## Workflow

1. `vendor/bin/mate tools:call rector-inspect` in any project you have not run Rector in yet. It is free and decides whether the next call is meaningful. The extension never generates a `rector.php`; without one, preview and apply both fail, and writing that config is a decision to raise with the user. When `composer_scripts` shows a `rector` or `fix` script, the flags it uses are what the maintainers intend, so match them rather than inventing an invocation.
2. `vendor/bin/mate tools:call rector-preview --path=src/Service --mode=detailed`. The dry-run diff is exactly what an apply would write. Read it and decide it is correct.
3. `vendor/bin/mate tools:call rector-apply --path=src/Service`, one directory at a time. Applying blind on a wide path turns a refactoring session into an unreviewable diff.
4. Run the tests (`phpunit-run`) and the analysis (`phpstan-analyse`) afterwards. A rule that is right in general can be wrong in one place. Report the changed files and the rules that fired.

`path` accepts a file or a directory; it must exist and be inside the project root, and is resolved relative to it. `debug` and `rulesSummary` are for investigating why a rule did or did not fire and make the payload much larger; leave them off by default.

## Reading

- `workflow` echoes `preview` or `apply`; `status` is `SUCCESS`, `FAILED`, or `TIMEOUT`.
- `exit_code: 2` is normal for a preview. Rector uses it to say there is code to change, and the tool already reports that as `SUCCESS`. It is not an error.
- `changed_file_count` and `changed_files` are what would change, or did. A preview with `changed_file_count: 0` is a complete answer, not a failure.
- `rules` names the rule classes that fired. When a change surprises you, that list names the rule responsible.
- `errors` and `error_count` are Rector's own per-file errors, usually a file it could not parse. A non-zero `error_count` makes the status `FAILED` even when other files changed cleanly.
- `mode`: `summary` for counts, `default` for changed files and rules, `detailed` to add the `diffs` and the executed command. Use `detailed` on a preview you intend to review, `summary` on an apply you already previewed.

## Failure paths

- "Rector is not available": no `vendor/bin/rector` and no `matesofmate_rector.custom_command`. Installing Rector is a dependency change to agree with the user first.
- "Rector configuration was not found": no `rector.php` or `rector.php.dist`, and none passed. Say so; do not write one unprompted.
- `rejected_input` in the payload: the `path` or `configuration` you passed does not exist or points outside the project root. That is a bad call, not a Rector result.
- `diagnostics` mentioning unparsable JSON: Rector did not produce the expected output and `error_output` holds what it printed, usually a fatal in the config or a version mismatch.
- `status: TIMEOUT` (exit code 124) after 300 seconds: narrow `path` instead of retrying the same scope. After a timeout on an apply, part of the work may already be on disk, so check the working tree before doing anything else.

## Rules

- Never call `rector-apply` on a path whose preview you have not read.
- Report changed files and applied rules; do not paste full diffs back unless asked.
