# 01 - Project Structure

## Goal

Create the base benchmark package structure inside the Symfony repository.

## Tasks

- [ ] Create `benchmark/` root directory.
- [ ] Create scenario, fixture, source, schema, and report directories.
- [ ] Add `benchmark/src/Command/BenchmarkRunCommand.php`.
- [ ] Register the command in Symfony.
- [ ] Add basic command options.
- [ ] Add a smoke test for command registration.

## Directory Structure

```text
benchmark/
  scenarios/
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

## Command

```bash
bin/console benchmark:run
```

## Required Options

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

## Acceptance Criteria

- [ ] Command appears in `bin/console list`.
- [ ] Command runs without executing AI.
- [ ] Command can print available scenario IDs once scenario loading exists.
