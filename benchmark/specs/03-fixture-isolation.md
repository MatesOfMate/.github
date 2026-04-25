# 03 - Fixture Isolation

## Goal

Ensure every benchmark run happens in a clean, isolated workspace.

## Tasks

- [ ] Implement `WorkspaceFactory`.
- [ ] Implement `FixtureCopier`.
- [ ] Implement `CommandExecutor` using Symfony Process.
- [ ] Implement `GitDiffCollector`.
- [ ] Initialize git in every workspace.
- [ ] Capture stdout, stderr, exit code, and duration for setup and verification commands.
- [ ] Add `--keep-workspace` support.

## Workspace Path

```text
var/benchmark/runs/<run-id>/<scenario-id>/workspace/
```

## Requirements

- [ ] Original fixtures are never mutated.
- [ ] Each repeat gets its own workspace.
- [ ] Setup commands run before assistant execution.
- [ ] Baseline commands can be captured before assistant execution.
- [ ] Final diff is stored after assistant execution.

## Acceptance Criteria

- [ ] Running the same scenario twice creates two separate workspaces.
- [ ] Git diff captures changed files and line counts.
- [ ] Failed setup commands stop the scenario with a clear error.
