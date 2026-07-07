# Symfony AI Mate Benchmark Suite - Implementation Plan

## Goal

Build a Symfony-based benchmark suite for validating AI assistants using `symfony/ai`, `symfony/ai-mate`, the Codex bridge, and the Claude Code bridge.

The benchmark must compare generated code quality, debugging ability, problem detection, Mate tool usage, token usage, runtime, and verification quality across multiple scenarios.

## Stack

- PHP 8.3+
- Symfony 7.3/7.4 or 8.0-ready
- symfony/ai
- symfony/ai-mate
- symfony/ai-claude-code-platform
- symfony/ai-codex-platform
- PHPUnit
- PHPStan
- Symfony Process
- JSON Schema for scenario definitions

## Deliverables

- Benchmark package inside the repository
- Scenario definition format
- Benchmark runner command
- AI provider adapters
- Mate/tool-call instrumentation
- Evaluators
- JSON and Markdown reports
- Initial benchmark scenarios

## Architecture

```text
benchmark/
  scenarios/
    code-generation/
    bug-finding/
    runtime-debugging/
    tool-usage/
  fixtures/
  src/
    Command/
    Runner/
    Scenario/
    Adapter/
    Evaluator/
    Metrics/
    Report/
  reports/
  schema/
```

## Main Command

```bash
bin/console benchmark:run
```

Supported options:

```bash
--scenario=<id>
--suite=<name>
--adapter=codex|claude|null
--model=<model>
--mate=enabled|disabled
--output=json|markdown
--repeat=1
--keep-workspace
```

## Scoring Model

```text
final_score =
  functional * 0.40
+ root_cause * 0.20
+ mate_tool_usage * 0.15
+ minimality * 0.10
+ verification * 0.10
+ efficiency * 0.05
```

Each subscore is in the range `0..5`.

## Milestones

- [ ] 01 - Project Structure
- [ ] 02 - Scenario Format
- [ ] 03 - Fixture Isolation
- [ ] 04 - AI Adapter Interface
- [ ] 05 - Mate Integration
- [ ] 06 - Metrics Collection
- [ ] 07 - Evaluators
- [ ] 08 - Scoring
- [ ] 09 - Reports
- [ ] 10 - Initial Scenarios
- [ ] 11 - CLI Examples
- [ ] 12 - Definition of Done

## Suggested Codex Execution Order

1. Implement milestones 01 and 02 first.
2. Add tests for scenario loading and schema validation.
3. Implement fixture isolation before any real AI execution.
4. Add `NullAdapter` before Codex/Claude adapters.
5. Add evaluators and reporting before optimizing Mate-specific metrics.
6. Add Codex and Claude adapters once the runner is stable.
7. Add Mate instrumentation and compare `mate=enabled` vs `mate=disabled`.
