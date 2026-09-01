## Rector Extension

Prefer these Mate tools over raw Rector CLI commands when the user is refactoring with Rector.

| User intent | Prefer |
|---|---|
| Check whether Rector is configured | `rector-inspect` |
| Preview Rector changes safely | `rector-preview` |
| Apply Rector changes | `rector-apply` |

### Guidance

- Use `rector-inspect` before running Rector in unfamiliar projects.
- Use `rector-preview` for review; it always runs Rector with `--dry-run`.
- Use `rector-apply` when Rector changes should be written.
- This extension returns encoded structured payloads through Mate's core encoder.
