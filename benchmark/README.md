# Symfony AI Mate Benchmark Suite

Benchmark suite for evaluating AI assistants (Codex, Claude Code, etc.) against code-generation, bug-finding, runtime-debugging and Mate-tool-usage scenarios.

See [`PLAN.md`](PLAN.md) for the overall plan and [`specs/`](specs) for milestone-by-milestone specifications.

## Status

This package is under active development. Milestones 01–12 are implemented:

- **01 — Project structure**: directory layout and the `benchmark:run` console command with all required options.
- **02 — Scenario format**: YAML scenarios validated against [`schema/scenario.schema.json`](schema/scenario.schema.json), loaded via `ScenarioLoader`/`ScenarioValidator`/`ScenarioRepository`.
- **03 — Fixture isolation**: per-attempt workspaces under `var/benchmark/runs/<run-id>/<scenario-id>/<attempt>/workspace/`, fixture copying, command execution with stdout/stderr/exit/duration capture, git-based diff collection against a sealed baseline, and `--keep-workspace` semantics.
- **04 — AI adapter interface + runner**: `AssistantAdapterInterface` with `AssistantRunInput`/`AssistantRunResult`/`TokenUsage`/`ToolCall`, `NullAdapter`, `AdapterRegistry`, and `ScenarioRunner` orchestrating fixture copy → setup → seal → adapter → diff → verify into a `RunOutcome`. `benchmark:run` now actually executes scenarios end-to-end (defaults to `--adapter=null`, use `--list` to only list).
- **05 — Mate integration**: `MateConfiguration` value object + per-workspace `MateConfigurationFactory` driving a `MateProvisioner` that writes a generated `composer.json`, runs `composer install` against the monorepo's local `src/*` packages, then `vendor/bin/mate init && vendor/bin/mate discover` to materialise a real `mcp.json` in the workspace (provisioned before sealing the baseline so it does not pollute the AI diff). The Claude adapter forwards that `mcp.json` to the CLI as `--mcp-config`. `MateMetricsCollector` aggregates tool calls into `MateMetrics` (count, names, first-call ms, errors, missing expected tools), and `--mate=enabled|disabled` drives the toggle.
- **06 — Metrics collection**: `MetricsBag` exposing every required + optional metric (with `null` for unsupported), pluggable `MetricsCollectorInterface` (`Duration`, `TokenUsage`, `ToolUsage`, `DiffMetrics`, `CommandResult`), and `MetricsAggregator` merging them into the `RunOutcome`.
- **07 — Evaluators**: `EvaluatorInterface` + `EvaluationInput`/`EvaluationResult` (0..5 score, pass/fail, explanation, evidence) and seven concrete judges — `Functional`, `RootCause`, `DiffMinimality`, `ForbiddenChanges`, `Verification`, `MateToolUsage`, `Efficiency`. Rule-based today; `RootCause` is the natural seam for an LLM-judge later.
- **08 — Scoring**: `ScoreWeights` (defaults from PLAN.md, scenario `evaluation.weights` override), `Score` exposing `finalScore`/`rawScore`/per-category/missing-evaluators/gate penalties plus `notApplicable` categories and the renormalised `effectiveWeights`, `ScoreCalculator` weighting evaluator output and applying gate multipliers, `EvaluationPipeline` running the seven judges, all wired through `ScenarioRunner` so every `RunOutcome` carries evaluations + a final score. See [How scoring works](#how-scoring-works).
- **Adapters (unnumbered)**: `ClaudeCodeAdapter` and `CodexAdapter` delegate to the `symfony/ai-claude-code-platform` and `symfony/ai-codex-platform` bridges (required at `^0.10@dev`) via `Platform::invoke()`. Both extend a single `Adapter\Platform\PlatformAdapter` base that maps the benchmark's `AssistantRunInput` to platform options (workspace `cwd`, Mate `mcp_config`, Codex `sandbox=workspace-write`) and converts the bridge's `MultiPartResult` back to `AssistantRunResult`: the assistant's final message lands in `stdout`, every tool call (name, arguments, MCP flag) is captured in `toolCalls`, and `TokenUsage` carries fresh/cached token counts plus `costUsd` when the CLI reports it. Binary paths can still be overridden via `BENCHMARK_CLAUDE_BIN` / `BENCHMARK_CODEX_BIN`. Tests stub `PlatformInterface` so no real model calls happen.
- **09 — Reports**: `ReportPipeline` writing `reports/<run-id>/results.json`, `summary.md`, plus `diffs/`, `logs/`, and `raw/` subdirectories per scenario. The Markdown summary covers every spec section (summary, adapter comparison, Mate toggle, scenario results table with per-category scores/cost/errors, tool usage, token usage split into fresh vs cached, slowest runs, failed scenarios incl. the failing pass command, most changed files); the JSON is deterministic and script-friendly and additionally carries `score.not_applicable`/`score.effective_weights`, a `response_excerpt` of the assistant's final message, compact tool-call info, and the baseline red-check result per scenario.
- **10 — Initial scenarios**: ten reproducible scenarios covering code-generation (`code.console-command`, `code.controller-route-test`, `code.service-with-di`), bug-finding (`bug.autowiring`, `bug.failing-phpunit`, `bug.invalid-env-config`, `bug.security-access-control`), runtime debugging (`runtime.monolog-exception`, `runtime.twig-variable-missing`) and one Mate-specific scenario (`mate.custom-tool-required`). Each fixture is a tiny pure-PHP project with a single deterministic verification command (`php tests/test.php`) and bakes the bug or missing functionality into one or two files.
- **11 — CLI examples**: documented invocation patterns in the README and a new `benchmark:compare` command diffing two `results.json` files (per-scenario score / tokens / duration / Mate-call deltas plus a run-level summary), plus a `--suite=all` alias that runs every scenario.
- **12 — Definition of Done**: every checklist item from `specs/12-definition-of-done.md` mapped to its location in code in [`DEFINITION-OF-DONE.md`](DEFINITION-OF-DONE.md); the offline-safe acceptance run (`bin/console benchmark:run --suite=all --adapter=null` ×2 + `benchmark:compare`) is reproducible end-to-end.

The benchmark suite scaffold is feature-complete against the original spec.

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

# List every scenario without executing them
bin/console benchmark:run --list

# Run everything against the deterministic NullAdapter (no real model calls)
bin/console benchmark:run --adapter=null
```

## CLI examples

```bash
# A single suite, one adapter, with Mate enabled / disabled
bin/console benchmark:run --suite=bug-finding --adapter=codex --mate=enabled
bin/console benchmark:run --suite=bug-finding --adapter=codex --mate=disabled

# All scenarios against Claude Code (--suite=all is equivalent to omitting --suite)
bin/console benchmark:run --suite=all --adapter=claude --mate=enabled

# A single scenario, three repetitions, against Codex
bin/console benchmark:run --scenario=bug.autowiring --adapter=codex --mate=enabled --repeat=3

# Compare two finished runs side-by-side (score, tokens, duration, Mate calls)
bin/console benchmark:compare reports/run-a/results.json reports/run-b/results.json
```

Reports for each run land in `reports/<run-id>/`, containing `results.json`, `summary.md`, plus per-scenario `diffs/`, `logs/` and `raw/` artefacts.

### `--mate=enabled` requirements

When Mate is enabled the runner provisions every workspace before invoking the assistant: it writes a generated `composer.json`, runs `composer install --no-scripts` (resolving `matesofmate/*` from the monorepo via `path` repositories), then runs `vendor/bin/mate init && vendor/bin/mate discover`. This produces a real `mcp.json` in the workspace, which the Claude adapter forwards via `--mcp-config`.

Implications:

- The first scenario per run pays a `composer install` cost (Composer's HTTP cache makes subsequent installs faster).
- `composer` and a working internet connection are required when Mate is enabled.
- **Codex + Mate** translates the workspace `mcp.json` into `-c mcp_servers.<name>.command/args=` overrides for the codex CLI and adds `--dangerously-bypass-approvals-and-sandbox`. The bypass flag is required because `codex exec` always pins `approval_policy=never`, which causes every MCP tool invocation to be auto-cancelled with "user cancelled MCP tool call". Codex documents the flag as "intended solely for running in environments that are externally sandboxed", which the per-attempt benchmark workspace under `var/benchmark/runs/<id>/<scenario>/<attempt>/workspace/` is. As a side effect Codex runs without a sandbox during these scenarios — only enable Mate-on-Codex against trusted scenarios.

## Scenario format

Scenarios are YAML files placed under `scenarios/<suite>/`. Every scenario is validated against [`schema/scenario.schema.json`](schema/scenario.schema.json). See [`specs/02-scenario-format.md`](specs/02-scenario-format.md) for the full spec and [`scenarios/bug-finding/bug.autowiring.yaml`](scenarios/bug-finding/bug.autowiring.yaml) for an example.

Required top-level keys: `id`, `suite`, `fixture`, `task`, `expected`. Optional: `difficulty`, `tags`, `evaluation`.

Format details worth knowing:

- **`expected.root_cause` is a list of keyword groups.** Each entry is either a single phrase or a list of synonymous phrases; a group counts as matched when *any* of its phrases appears in the assistant's explanation, and the root-cause score is proportional to the fraction of matched groups.
- **`tags: [mate-only]`** marks scenarios that specifically measure Mate tool usage; they are skipped (with a console notice) when the run uses `--mate=disabled`.
- **`expected.forbidden_files_changed`** protects files (typically the tests) from tampering: before verification the runner restores them to their baseline content, so editing them cannot fake a pass — the tampering stays visible in the collected diff and is gated by the `forbidden_changes` evaluator.

## How runs are executed and validated

- **Baseline red-check**: before the assistant runs, every `expected.pass_commands` entry is executed once. If they *all already succeed*, the attempt is marked with the `invalid_scenario` status — the scenario does not reproduce a failure and proves nothing. Invalid scenarios are counted separately in the reports and excluded from score averages.
- **"Benchmark rules" prompt appendix**: every assistant receives the scenario prompt plus a standardised appendix stating how the work is verified (the exact `pass_commands`), which files must not be modified, and that work happens only inside the workspace. Benchmarks measure ability, not guessing the harness.
- **Forbidden-file restore**: protected files are restored to their baseline content *before* the verification commands run (see above).

## How scoring works

Every attempt is judged by seven rule-based evaluators, each returning 0..5. Category scores are combined using the weights (defaults: functional 0.40, root_cause 0.20, mate_tool_usage 0.15, minimality 0.10, verification 0.10, efficiency 0.05; overridable per scenario via `evaluation.weights`):

- **Gates**: two evaluators act as multipliers on the final score instead of mere weights — a `forbidden_changes` failure multiplies the score by **0.0**, a `functional` failure by **0.25**. Gate penalties are reported in `score.gate_penalties`.
- **Not-applicable categories renormalise the weights**: when a category cannot be judged for a run (e.g. `mate_tool_usage` under `--mate=disabled`), it is excluded and the remaining weights are renormalised instead of scoring 0 — so a `--mate=disabled` run can still reach 5.0 and stays comparable across configurations. Excluded categories are listed in `score.not_applicable`, the weights actually applied in `score.effective_weights`.
- **Efficiency is only scored for functionally successful runs** (a fast no-op is a failure, not efficient) and is based on wall-clock time plus *fresh* tokens — cached prompt tokens are excluded, because cache reads are how CLI agents are supposed to work and cost a fraction of fresh tokens.
- **Verification requires executed commands**: the evaluator searches the captured tool calls for actual executions of the scenario's `pass_commands` (or generic test runners). Merely *mentioning* a command in the final response earns nothing.

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
