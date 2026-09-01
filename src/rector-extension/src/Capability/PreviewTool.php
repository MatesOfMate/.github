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
use Symfony\AI\Mate\Attribute\MateTool;

/**
 * Runs Rector in mandatory dry-run mode and returns structured preview output.
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class PreviewTool
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
    #[MateTool(
        name: 'rector-preview',
        title: 'Rector Preview',
        description: 'Preview Rector refactors with --dry-run. This tool never applies source-code changes.'
    )]
    public function execute(
        ?string $path = null,
        ?string $configuration = null,
        bool $debug = false,
        bool $rulesSummary = false,
        string $mode = 'default',
    ): string {
        return $this->workflow->run(true, $path, $configuration, $debug, $rulesSummary, $mode);
    }
}
