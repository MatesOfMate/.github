# 10 - Initial Scenarios

## Goal

Create the first benchmark scenario suite.

## Tasks

- [ ] Add 10 scenarios.
- [ ] Add a fixture for every scenario.
- [ ] Add expected pass commands.
- [ ] Add expected root-cause hints where applicable.
- [ ] Add expected and forbidden file changes.

## Scenarios

### Code Generation

- [ ] `code.console-command`
- [ ] `code.controller-route-test`
- [ ] `code.service-with-di`

### Bug Finding

- [ ] `bug.autowiring`
- [ ] `bug.failing-phpunit`
- [ ] `bug.invalid-env-config`
- [ ] `bug.security-access-control`

### Runtime Debugging

- [ ] `runtime.twig-variable-missing`
- [ ] `runtime.monolog-exception`

### Mate-specific

- [ ] `mate.custom-tool-required`

## Acceptance Criteria

- [ ] All scenarios can be loaded by the runner.
- [ ] All fixtures are isolated and reproducible.
- [ ] Each scenario has at least one deterministic verification command.
- [ ] At least one scenario benefits clearly from Mate tools.
