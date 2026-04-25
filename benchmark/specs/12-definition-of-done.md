# 12 - Definition of Done

## Goal

Define final acceptance criteria for the benchmark suite.

## Checklist

- [ ] `bin/console benchmark:run` works.
- [ ] At least 10 scenarios run end-to-end.
- [ ] Codex and Claude adapters share one interface.
- [ ] `NullAdapter` exists for tests and runner development.
- [ ] Mate can be enabled or disabled per run.
- [ ] Results are exported as JSON.
- [ ] Markdown report is generated.
- [ ] Diffs are persisted.
- [ ] Logs are persisted.
- [ ] Raw assistant output is persisted.
- [ ] Tool calls are persisted.
- [ ] Token usage is persisted when available.
- [ ] Baseline vs Mate comparison is possible.
- [ ] Scenario schema is documented.
- [ ] Fixture isolation prevents mutation of source fixtures.
- [ ] Evaluators return deterministic scores.
- [ ] Failed scenarios include actionable error messages.

## Final Acceptance Test

Run:

```bash
bin/console benchmark:run --suite=all --adapter=null --mate=disabled
bin/console benchmark:run --suite=bug-finding --adapter=codex --mate=enabled
bin/console benchmark:run --suite=bug-finding --adapter=codex --mate=disabled
```

Then verify:

- [ ] Three report directories were created.
- [ ] Each report contains `results.json` and `summary.md`.
- [ ] The Codex Mate-enabled and Mate-disabled reports can be compared.
