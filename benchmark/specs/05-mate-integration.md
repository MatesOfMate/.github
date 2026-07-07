# 05 - Mate Integration

## Goal

Integrate `symfony/ai-mate` so benchmark runs can compare Mate-enabled and Mate-disabled execution.

## Tasks

- [ ] Implement `MateConfigurationFactory`.
- [ ] Implement `MateServerManager` if a long-running MCP server is required.
- [ ] Add per-workspace Mate configuration.
- [ ] Expose Mate config to Codex adapter.
- [ ] Expose Mate config to Claude Code adapter.
- [ ] Collect Mate tool calls.
- [ ] Track Mate tool errors.

## Metrics

```json
{
  "mate_enabled": true,
  "mate_tool_call_count": 5,
  "mate_tool_names": [
    "symfony_logs",
    "symfony_container",
    "symfony_profiler"
  ],
  "first_mate_tool_call_ms": 2310,
  "tool_errors": 0
}
```

## Requirements

- [ ] `--mate=enabled` configures Mate.
- [ ] `--mate=disabled` prevents Mate usage.
- [ ] Mate-specific scenarios can require expected tools.
- [ ] Tool-call data is persisted in result JSON.

## Acceptance Criteria

- [ ] Same scenario can be run with Mate enabled and disabled.
- [ ] Reports show Mate tool names and counts.
- [ ] Missing expected Mate tools can reduce the score.
