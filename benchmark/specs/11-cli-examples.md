# 11 - CLI Examples

## Goal

Document and support common benchmark commands.

## Tasks

- [ ] Add CLI examples to project docs.
- [ ] Add `benchmark:compare` command if feasible.
- [ ] Support suite-level runs.
- [ ] Support scenario-level runs.
- [ ] Support repeated runs.
- [ ] Support Mate enabled vs disabled comparison.

## Examples

```bash
bin/console benchmark:run --suite=bug-finding --adapter=codex --mate=enabled
bin/console benchmark:run --suite=bug-finding --adapter=codex --mate=disabled
bin/console benchmark:run --suite=all --adapter=claude --mate=enabled
bin/console benchmark:run --scenario=bug.autowiring --adapter=codex --mate=enabled --repeat=3
bin/console benchmark:compare benchmark/reports/run-a/results.json benchmark/reports/run-b/results.json
```

## Acceptance Criteria

- [ ] Examples work as documented.
- [ ] Invalid options produce helpful errors.
- [ ] Compare command highlights score, token, duration, and Mate usage differences.
