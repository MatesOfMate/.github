# 09 - Reports

## Goal

Generate useful machine-readable and human-readable benchmark reports.

## Tasks

- [ ] Implement `JsonReportWriter`.
- [ ] Implement `MarkdownReportWriter`.
- [ ] Persist logs per scenario.
- [ ] Persist diffs per scenario.
- [ ] Persist raw assistant stdout/stderr.
- [ ] Add summary tables.
- [ ] Add adapter and Mate comparison sections.

## Output Structure

```text
benchmark/reports/<run-id>/
  results.json
  summary.md
  diffs/
    <scenario-id>.diff
  logs/
    <scenario-id>.log
  raw/
    <scenario-id>.stdout.txt
    <scenario-id>.stderr.txt
```

## Markdown Report Sections

- [ ] Summary
- [ ] Adapter comparison
- [ ] Mate enabled vs disabled
- [ ] Scenario results
- [ ] Tool usage
- [ ] Token usage
- [ ] Slowest runs
- [ ] Failed scenarios
- [ ] Most changed files

## Acceptance Criteria

- [ ] `results.json` can be consumed by scripts.
- [ ] `summary.md` is readable in GitHub.
- [ ] Diffs and logs are linked from the summary.
