<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Capability;

use MatesOfMate\RectorExtension\Workflow\RectorWorkflow;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Applies Rector changes through the write-capable tool.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ApplyTool
{
    public function __construct(
        private readonly RectorWorkflow $workflow,
    ) {
    }

    /**
     * @param string|null $path          Optional file or directory inside the project root. Defaults to Rector configuration scope.
     * @param string|null $configuration Optional Rector configuration path inside the project root. Defaults to detected config.
     * @param bool        $debug         Include Rector debug output. Disabled by default.
     * @param bool        $rulesSummary  Include Rector rules summary. Disabled by default.
     * @param string      $mode          output detail level: default, summary, or detailed
     */
    #[McpTool(
        name: 'rector-apply',
        title: 'Rector Apply',
        description: 'Apply Rector refactors. This is a write operation.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false)
    )]
    public function execute(
        ?string $path = null,
        ?string $configuration = null,
        bool $debug = false,
        bool $rulesSummary = false,
        #[Schema(description: 'Output detail level', enum: ['default', 'summary', 'detailed'])]
        string $mode = 'default',
    ): string {
        return $this->workflow->run(false, $path, $configuration, $debug, $rulesSummary, $mode);
    }
}
