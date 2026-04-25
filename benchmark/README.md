# Symfony AI Mate Benchmark Suite

Benchmark suite for evaluating AI assistants (Codex, Claude Code, etc.) against code-generation, bug-finding, runtime-debugging and Mate-tool-usage scenarios.

See [`PLAN.md`](PLAN.md) for the overall plan and [`specs/`](specs) for milestone-by-milestone specifications.

## Status

This package is under active development. Only milestones 01 and 02 are implemented:

- **01 — Project structure**: directory layout and the `benchmark:run` console command with all required options.
- **02 — Scenario format**: YAML scenarios validated against [`schema/scenario.schema.json`](schema/scenario.schema.json), loaded via `ScenarioLoader`/`ScenarioValidator`/`ScenarioRepository`.

AI execution, fixture isolation, evaluators, scoring and reporting will be added in subsequent milestones.

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
