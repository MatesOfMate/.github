# 06 - Metrics Collection

## Goal

Collect consistent, adapter-independent benchmark metrics.

## Tasks

- [ ] Implement `DurationCollector`.
- [ ] Implement `TokenUsageCollector`.
- [ ] Implement `ToolUsageCollector`.
- [ ] Implement `DiffMetricsCollector`.
- [ ] Implement `CommandResultCollector`.
- [ ] Implement `MetricsBag` or equivalent aggregate object.

## Required Metrics

- [ ] duration_ms
- [ ] input_tokens
- [ ] output_tokens
- [ ] total_tokens
- [ ] tool_call_count
- [ ] mate_tool_call_count
- [ ] mate_tool_names
- [ ] files_changed_count
- [ ] diff_added_lines
- [ ] diff_removed_lines
- [ ] commands_passed
- [ ] commands_failed

## Optional Metrics

- [ ] time_to_first_tool_call_ms
- [ ] time_to_first_code_change_ms
- [ ] first_mate_tool_call_ms
- [ ] redundant_tool_call_count
- [ ] tool_error_count

## Acceptance Criteria

- [ ] Metrics are included in every result JSON.
- [ ] Unsupported metrics are represented as `null`, not omitted silently.
- [ ] Metrics are adapter-independent where possible.
