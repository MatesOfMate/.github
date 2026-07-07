# 02 - Scenario Format

## Goal

Define a reproducible scenario format for benchmark tasks.

## Tasks

- [ ] Create `benchmark/schema/scenario.schema.json`.
- [ ] Use YAML for scenario files.
- [ ] Implement `Scenario` value object.
- [ ] Implement `ScenarioLoader`.
- [ ] Implement `ScenarioValidator`.
- [ ] Implement `ScenarioRepository`.
- [ ] Add PHPUnit tests for valid and invalid scenarios.

## Example Scenario

```yaml
id: bug.autowiring.private-service
suite: bug-finding
difficulty: medium

fixture:
  path: fixtures/bug-autowiring-private-service
  setup:
    - composer install
  baseline:
    - vendor/bin/phpunit

task:
  prompt: |
    The application fails when running the tests.
    Find the root cause and fix it.

expected:
  pass_commands:
    - vendor/bin/phpunit
  root_cause:
    - autowiring failure
    - private or missing service definition
  expected_files_changed:
    - config/services.yaml
  forbidden_files_changed:
    - composer.json
    - composer.lock

evaluation:
  weights:
    functional: 40
    root_cause: 20
    mate_tool_usage: 15
    minimality: 10
    verification: 10
    efficiency: 5
```

## Acceptance Criteria

- [ ] Invalid scenarios fail validation with readable errors.
- [ ] Scenarios can be loaded by suite or by ID.
- [ ] Scenario schema is committed and documented.
