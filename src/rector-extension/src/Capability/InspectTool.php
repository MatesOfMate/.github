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

use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * Inspects Rector availability and configuration for the current project.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class InspectTool
{
    public function __construct(
        private readonly RectorDiscovery $discovery,
        private readonly ToonFormatter $formatter,
    ) {
    }

    #[McpTool(
        name: 'rector-inspect',
        title: 'Rector Inspect',
        description: 'Inspect project-local Rector installation, configuration, Composer scripts, and safe execution strategy without running Rector.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(): string
    {
        return $this->formatter->formatInspection($this->discovery->inspect());
    }
}
