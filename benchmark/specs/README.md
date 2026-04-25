# Benchmark Specs Index

This directory contains one spec file per implementation milestone.

## Milestone Checklist

- [ ] [01 - Project Structure](01-project-structure.md)  
  Create the benchmark package structure and the first Symfony console command.

- [ ] [02 - Scenario Format](02-scenario-format.md)  
  Define YAML scenarios, JSON schema, loader, validator, and repository classes.

- [ ] [03 - Fixture Isolation](03-fixture-isolation.md)  
  Copy fixtures into isolated workspaces and collect command output and diffs.

- [ ] [04 - AI Adapter Interface](04-ai-adapter-interface.md)  
  Add a shared adapter contract for Codex, Claude Code, and Null adapter runs.

- [ ] [05 - Mate Integration](05-mate-integration.md)  
  Configure `symfony/ai-mate`, start or prepare MCP usage, and collect Mate tool calls.

- [ ] [06 - Metrics Collection](06-metrics-collection.md)  
  Capture duration, tokens, tool calls, diffs, files changed, and command results.

- [ ] [07 - Evaluators](07-evaluators.md)  
  Implement functional, root-cause, diff, verification, Mate usage, and efficiency evaluators.

- [ ] [08 - Scoring](08-scoring.md)  
  Combine evaluator results into weighted benchmark scores.

- [ ] [09 - Reports](09-reports.md)  
  Generate JSON, Markdown, logs, diffs, and run summaries.

- [ ] [10 - Initial Scenarios](10-initial-scenarios.md)  
  Add the first ten code-generation, bug-finding, runtime-debugging, and Mate-specific scenarios.

- [ ] [11 - CLI Examples](11-cli-examples.md)  
  Document and implement common benchmark and comparison commands.

- [ ] [12 - Definition of Done](12-definition-of-done.md)  
  Final acceptance checklist for the benchmark suite.

## Recommended First Codex Prompt

```text
Implement milestones 01 and 02 from the specs directory.

Create the benchmark directory structure, scenario YAML format, JSON schema, Scenario value object, ScenarioLoader, ScenarioValidator, ScenarioRepository, and a basic Symfony console command `benchmark:run` that loads scenarios and prints their IDs.

Do not implement AI execution yet.
Add PHPUnit tests for loading and validating scenarios.
```
