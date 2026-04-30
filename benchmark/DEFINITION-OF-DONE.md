# Definition of Done

This file maps every checklist item from
[`specs/12-definition-of-done.md`](specs/12-definition-of-done.md) to the place
in the codebase that satisfies it. Use it as the entry point when reviewing the
benchmark suite.

## Checklist

| Item | Where it is satisfied |
|---|---|
| `bin/console benchmark:run` works | [`bin/console`](bin/console) wires every collaborator and registers `benchmark:run` via [`src/Command/BenchmarkRunCommand.php`](src/Command/BenchmarkRunCommand.php). |
| At least 10 scenarios run end-to-end | [`scenarios/`](scenarios) ships ten scenarios across code-generation, bug-finding, runtime debugging and Mate-specific suites; the final acceptance test below executes all of them. |
| Codex and Claude adapters share one interface | Both extend [`src/Adapter/Process/ProcessAdapter.php`](src/Adapter/Process/ProcessAdapter.php) and implement [`src/Adapter/AssistantAdapterInterface.php`](src/Adapter/AssistantAdapterInterface.php) (see [`src/Adapter/ClaudeCodeAdapter.php`](src/Adapter/ClaudeCodeAdapter.php) and [`src/Adapter/CodexAdapter.php`](src/Adapter/CodexAdapter.php)). |
| `NullAdapter` exists for tests and runner development | [`src/Adapter/NullAdapter.php`](src/Adapter/NullAdapter.php), registered by default in `bin/console`. |
| Mate can be enabled or disabled per run | `--mate=enabled\|disabled` flag on `benchmark:run`, plumbed through [`src/Mate/MateConfigurationFactory.php`](src/Mate/MateConfigurationFactory.php). |
| Results are exported as JSON | [`src/Report/JsonReportWriter.php`](src/Report/JsonReportWriter.php) writes `reports/<run-id>/results.json`. |
| Markdown report is generated | [`src/Report/MarkdownReportWriter.php`](src/Report/MarkdownReportWriter.php) writes `reports/<run-id>/summary.md`. |
| Diffs are persisted | [`src/Report/ArtifactsWriter.php`](src/Report/ArtifactsWriter.php) writes `reports/<run-id>/diffs/<scenario>-attempt-N.diff`. |
| Logs are persisted | Same writer emits `reports/<run-id>/logs/<scenario>-attempt-N.log` (setup, baseline and verify command output). |
| Raw assistant output is persisted | Same writer emits `reports/<run-id>/raw/<scenario>-attempt-N.stdout.txt` and `.stderr.txt`. |
| Tool calls are persisted | The JSON report stores every assistant tool call (name + arguments + errored flag) under `scenarios[].assistant.tool_calls`. |
| Token usage is persisted when available | [`src/Metrics/Collector/TokenUsageCollector.php`](src/Metrics/Collector/TokenUsageCollector.php) emits `input_tokens`/`output_tokens`/`total_tokens` (or `null`); the JSON report carries them under `scenarios[].metrics`. |
| Baseline vs Mate comparison is possible | Run twice with `--mate=enabled` and `--mate=disabled`, then diff with [`src/Command/BenchmarkCompareCommand.php`](src/Command/BenchmarkCompareCommand.php). |
| Scenario schema is documented | [`schema/scenario.schema.json`](schema/scenario.schema.json) plus the README scenario-format section. |
| Fixture isolation prevents mutation of source fixtures | [`src/Runner/FixtureCopier.php`](src/Runner/FixtureCopier.php) mirrors fixtures into per-attempt workspaces (under `var/benchmark/runs/<run-id>/<scenario-id>/<attempt>/workspace/`) without ever writing back. |
| Evaluators return deterministic scores | All seven judges in [`src/Evaluator/`](src/Evaluator) are pure rule-based functions of the run outcome; weighted by [`src/Scoring/ScoreCalculator.php`](src/Scoring/ScoreCalculator.php). |
| Failed scenarios include actionable error messages | [`src/Runner/Exception/CommandFailedException.php`](src/Runner/Exception/CommandFailedException.php) and [`src/Runner/Exception/FixtureNotFoundException.php`](src/Runner/Exception/FixtureNotFoundException.php) surface exact command, exit code, and stderr; runner converts them into `RunStatus::SetupError`/`AdapterError` outcomes with the same message in `errorMessage`. |

## Final acceptance test

Run from `benchmark/` (the Codex/Claude variants exist for users with API access; the `null` run is the offline-safe canonical check):

```bash
bin/console benchmark:run --suite=all --adapter=null --mate=disabled
bin/console benchmark:run --suite=all --adapter=null --mate=enabled
bin/console benchmark:compare \
    reports/<run-1>/results.json \
    reports/<run-2>/results.json
```

Expected:

- Two report directories under `reports/<run-id>/`.
- Each contains `results.json`, `summary.md`, `diffs/`, `logs/` and `raw/`.
- `benchmark:compare` prints a per-scenario diff (score, tokens, duration, Mate calls) plus a run-level summary, so the Mate-enabled vs Mate-disabled comparison required by the spec is reproducible end-to-end.

For real-model checks, swap `--adapter=null` for `--adapter=codex` or `--adapter=claude` and ensure the corresponding CLI is installed and authenticated. Binary paths and extra arguments are configurable via `BENCHMARK_CODEX_BIN`/`BENCHMARK_CODEX_ARGS` and `BENCHMARK_CLAUDE_BIN`/`BENCHMARK_CLAUDE_ARGS`.

## Test status

```text
phpunit -c phpunit.xml.dist
OK (150 tests, 507 assertions)
```
