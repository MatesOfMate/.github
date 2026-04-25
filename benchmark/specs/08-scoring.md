# 08 - Scoring

## Goal

Combine evaluator results into a single weighted benchmark score.

## Tasks

- [ ] Implement `ScoreCalculator`.
- [ ] Read weights from scenario config.
- [ ] Provide default weights.
- [ ] Normalize evaluator scores.
- [ ] Persist final and per-category scores.

## Default Formula

```text
final_score =
  functional * 0.40
+ root_cause * 0.20
+ mate_tool_usage * 0.15
+ minimality * 0.10
+ verification * 0.10
+ efficiency * 0.05
```

## Score Categories

- [ ] functional
- [ ] root_cause
- [ ] mate_tool_usage
- [ ] minimality
- [ ] verification
- [ ] efficiency

## Acceptance Criteria

- [ ] Final score is deterministic.
- [ ] Missing evaluator data is handled explicitly.
- [ ] Per-category scores are visible in JSON and Markdown reports.
