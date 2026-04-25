# 04 - AI Adapter Interface

## Goal

Create a shared execution interface for Codex, Claude Code, and test adapters.

## Tasks

- [ ] Create `AssistantAdapterInterface`.
- [ ] Create `AssistantRunInput`.
- [ ] Create `AssistantRunResult`.
- [ ] Create `TokenUsage`.
- [ ] Create `ToolCall` value object.
- [ ] Implement `NullAdapter`.
- [ ] Add adapter factory/resolver.

## Interface

```php
interface AssistantAdapterInterface
{
    public function run(AssistantRunInput $input): AssistantRunResult;
}
```

## Input Fields

- [ ] workspace path
- [ ] prompt
- [ ] model
- [ ] Mate enabled flag
- [ ] environment variables
- [ ] timeout

## Result Fields

- [ ] success
- [ ] stdout
- [ ] stderr
- [ ] exit code
- [ ] duration milliseconds
- [ ] token usage or null
- [ ] tool calls

## Acceptance Criteria

- [ ] Runner can execute a scenario with `NullAdapter`.
- [ ] Adapter failures are captured as benchmark results, not fatal crashes.
- [ ] Token usage may be null for unsupported adapters.
