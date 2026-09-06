CHANGELOG
=========

0.7.0
-----

 * Group reported changes by the Rector rule that produced them
 * Add `rector-run-detail` to read the diffs of a run by its id
 * Keep the last 20 runs under Mate's cache directory
 * Stop repeating Rector's raw JSON output inside the detailed response
 * Add a `rector-refactoring` skill covering the inspect, preview, apply order and result interpretation
 * Support symfony/ai-mate 0.13
 * Replace the `#[McpTool]` attribute with Mate's native `#[MateTool]`
 * Drop the `#[Schema]` parameter attributes and MCP tool annotations, which have no equivalent in the native CLI

0.6.0
-----

 * Support symfony/ai-mate 0.12

0.5.0
-----

 * Introduce Rector extension with inspect, preview, and apply tools
