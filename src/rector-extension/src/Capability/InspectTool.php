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
use Symfony\AI\Mate\Attribute\MateTool;

/**
 * Inspects Rector availability and configuration for the current project.
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class InspectTool
{
    public function __construct(
        private readonly RectorDiscovery $discovery,
        private readonly ToonFormatter $formatter,
    ) {
    }

    #[MateTool(
        name: 'rector-inspect',
        title: 'Rector Inspect',
        description: 'Inspect project-local Rector installation, configuration, Composer scripts, and safe execution strategy without running Rector.'
    )]
    public function execute(): string
    {
        return $this->formatter->formatInspection($this->discovery->inspect());
    }
}
