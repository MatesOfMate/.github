# Symfony AI Mate Benchmark Suite

Benchmark suite for evaluating AI assistants (Codex, Claude Code, etc.) against code-generation, bug-finding, runtime-debugging and Mate-tool-usage scenarios.

See [`PLAN.md`](PLAN.md) for the overall plan and [`specs/`](specs) for milestone-by-milestone specifications.

## Status

This package is under active development. Milestones 01–08 are implemented:

- **01 — Project structure**: directory layout and the `benchmark:run` console command with all required options.
- **02 — Scenario format**: YAML scenarios validated against [`schema/scenario.schema.json`](schema/scenario.schema.json), loaded via `ScenarioLoader`/`ScenarioValidator`/`ScenarioRepository`.
- **03 — Fixture isolation**: per-attempt workspaces under `var/benchmark/runs/<run-id>/<scenario-id>/<attempt>/workspace/`, fixture copying, command execution with stdout/stderr/exit/duration capture, git-based diff collection against a sealed baseline, and `--keep-workspace` semantics.
- **04 — AI adapter interface + runner**: `AssistantAdapterInterface` with `AssistantRunInput`/`AssistantRunResult`/`TokenUsage`/`ToolCall`, `NullAdapter`, `AdapterRegistry`, and `ScenarioRunner` orchestrating fixture copy → setup → seal → adapter → diff → verify into a `RunOutcome`. `benchmark:run` now actually executes scenarios end-to-end (defaults to `--adapter=null`, use `--list` to only list).
- **05 — Mate integration**: `MateConfiguration` value object + per-workspace `MateConfigurationFactory` writing `.mate/config.json` (provisioned before sealing the baseline so it doesn't pollute the AI diff), `MateMetricsCollector` aggregating tool calls into `MateMetrics` (count, names, first-call ms, errors, missing expected tools), and `--mate=enabled|disabled` driving the toggle.
- **06 — Metrics collection**: `MetricsBag` exposing every required + optional metric (with `null` for unsupported), pluggable `MetricsCollectorInterface` (`Duration`, `TokenUsage`, `ToolUsage`, `DiffMetrics`, `CommandResult`), and `MetricsAggregator` merging them into the `RunOutcome`.
- **07 — Evaluators**: `EvaluatorInterface` + `EvaluationInput`/`EvaluationResult` (0..5 score, pass/fail, explanation, evidence) and seven concrete judges — `Functional`, `RootCause`, `DiffMinimality`, `ForbiddenChanges`, `Verification`, `MateToolUsage`, `Efficiency`. Rule-based today; `RootCause` is the natural seam for an LLM-judge later.
- **08 — Scoring**: `ScoreWeights` (defaults from PLAN.md, scenario `evaluation.weights` override), `Score` exposing `finalScore`/`rawScore`/per-category/missing-evaluators/gate penalties, `ScoreCalculator` weighting evaluator output, `EvaluationPipeline` running the seven judges, all wired through `ScenarioRunner` so every `RunOutcome` carries evaluations + a final score.
- **Adapters (unnumbered)**: `ClaudeCodeAdapter` driving `claude --print --output-format=stream-json` and `CodexAdapter` driving `codex exec --json`, sharing a `ProcessAdapter` base. Best-effort JSONL parsers (`ClaudeStreamJsonParser`, `CodexJsonParser`) extract token usage and tool calls. Binary paths and extra flags are configurable via `BENCHMARK_CLAUDE_BIN`/`BENCHMARK_CLAUDE_ARGS` and `BENCHMARK_CODEX_BIN`/`BENCHMARK_CODEX_ARGS`. Tests use offline PHP fakes so the suite never makes real model calls.
- **09 — Reports**: `ReportPipeline` writing `reports/<run-id>/results.json`, `summary.md`, plus `diffs/`, `logs/`, and `raw/` subdirectories per scenario. The Markdown summary covers every spec section (summary, adapter comparison, Mate toggle, scenario results table, tool usage, token usage, slowest runs, failed scenarios, most changed files); the JSON is deterministic and script-friendly.
- **10 — Initial scenarios**: ten reproducible scenarios covering code-generation (`code.console-command`, `code.controller-route-test`, `code.service-with-di`), bug-finding (`bug.autowiring`, `bug.failing-phpunit`, `bug.invalid-env-config`, `bug.security-access-control`), runtime debugging (`runtime.monolog-exception`, `runtime.twig-variable-missing`) and one Mate-specific scenario (`mate.custom-tool-required`). Each fixture is a tiny pure-PHP project with a single deterministic verification command (`php tests/test.php`) and bakes the bug or missing functionality into one or two files.

CLI examples will be added in milestone 11.

## Layout

```
benchmark/
  bin/console              # Symfony Console entry point
  scenarios/               # Scenario YAML files, organised by suite
  fixtures/                # Per-scenario fixture projects (added in milestone 03)
  schema/                  # JSON schema for scenarios
  src/
    Command/               # Symfony Console commands
    Scenario/              # Scenario value object, loader, validator, repository
    Adapter/               # AI adapters (milestone 04)
    Runner/                # Scenario runner (milestone 03)
    Evaluator/             # Evaluators (milestone 07)
    Metrics/               # Metric collection (milestone 06)
    Report/                # Reports (milestone 09)
  reports/                 # Generated reports (milestone 09)
  tests/                   # PHPUnit tests
```

## Quick start

```bash
cd benchmark
composer install
composer test

# List all scenarios
bin/console benchmark:run

# Filter by suite
bin/console benchmark:run --suite=bug-finding

# Filter by scenario id
bin/console benchmark:run --scenario=bug.autowiring.private-service
```

## Scenario format

Scenarios are YAML files placed under `scenarios/<suite>/`. Every scenario is validated against [`schema/scenario.schema.json`](schema/scenario.schema.json). See [`specs/02-scenario-format.md`](specs/02-scenario-format.md) for the full spec and [`scenarios/bug-finding/bug.autowiring.private-service.yaml`](scenarios/bug-finding/bug.autowiring.private-service.yaml) for an example.

Required top-level keys: `id`, `suite`, `fixture`, `task`, `expected`. Optional: `difficulty`, `tags`, `evaluation`.

## Available command options

| Option              | Default     | Description                                              |
| ------------------- | ----------- | -------------------------------------------------------- |
| `--scenario=<id>`   | —           | Run a single scenario by id.                             |
| `--suite=<name>`    | —           | Run all scenarios from a given suite.                    |
| `--adapter=<name>`  | `null`      | AI adapter: `codex`, `claude` or `null`.                 |
| `--model=<id>`      | `default`   | Model identifier passed to the adapter.                  |
| `--mate=<state>`    | `enabled`   | Mate integration: `enabled` or `disabled`.               |
| `--output=<format>` | `markdown`  | Report format: `json` or `markdown`.                     |
| `--repeat=<n>`      | `1`         | Number of times each scenario is executed.               |
| `--keep-workspace`  | off         | Keep the isolated workspace directory after execution.   |
