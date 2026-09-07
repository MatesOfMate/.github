CHANGELOG
=========

0.7.0
-----

 * Move development branch to 0.7.x-dev for the 0.13 release line
 * Add `Cache\RunCache`, a small on-disk store keyed by a generated run id, so a tool can hand back a compact grouped response while keeping the full detail readable through a follow-up call; a failed write now throws instead of returning an id that can never be loaded back

0.6.0
-----

 * Move development branch to 0.6.x-dev for the 0.12 release line

0.4.0
-----

 * Move development branch to 0.4.x-dev for the 0.11 release line

0.3.0
-----

 * Stabilize dependency constraints for the 0.3 release line
 * Stop tracking composer.lock for the library package

0.1.0
-----

 * Add ProcessExecutor for CLI tool execution with PHP binary reuse
 * Add ConfigurationDetector for auto-detecting config files
 * Add MessageTruncator for token-efficient output with smart prefix preservation
 * Add ProcessResult DTO for command execution results
