<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\PhpStanExtension\Capability;

use MatesOfMate\PhpStanExtension\Config\ConfigurationDetector;
use MatesOfMate\PhpStanExtension\Runner\PhpStanRunner;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * Generates a PHPStan baseline file to suppress existing errors.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class GenerateBaselineTool
{
    use BuildsPhpstanArguments;

    public function __construct(
        private readonly PhpStanRunner $runner,
        private readonly ConfigurationDetector $configDetector,
    ) {
    }

    #[McpTool(
        name: 'phpstan-generate-baseline',
        description: 'Generate a PHPStan baseline file that suppresses existing errors, allowing incremental adoption of stricter rules. Also checks whether the baseline is properly imported in the PHPStan configuration.',
    )]
    public function execute(
        #[Schema(
            description: 'Path to PHPStan configuration file (defaults to auto-detection)'
        )]
        ?string $configuration = null,
        #[Schema(
            description: 'PHPStan rule level (0-9, higher is stricter)',
            minimum: 0,
            maximum: 9
        )]
        ?int $level = null,
        #[Schema(
            description: 'Path or directory to analyse (defaults to configured paths)'
        )]
        ?string $path = null,
        #[Schema(
            description: 'Output file path for the generated baseline'
        )]
        string $baselineFile = 'phpstan-baseline.neon',
    ): string {
        $configFile = $configuration ?? $this->configDetector->detect();

        $args = $this->buildPhpstanArgs(
            path: $path,
            configuration: $configFile,
            level: $level,
        );
        $args[] = '--generate-baseline='.$baselineFile;

        $runResult = $this->runner->run('analyse', $args);
        $success = 0 === $runResult->exitCode;
        $baselineImported = $this->isBaselineImported($configFile, $baselineFile);

        $result = [
            'success' => $success,
            'baseline_file' => $baselineFile,
            'baseline_imported' => $baselineImported,
        ];

        if ($success) {
            $result['message'] = 'Baseline generated successfully';
            if (!$baselineImported) {
                $result['hint'] = 'Add "'.basename($baselineFile).'" to your PHPStan configuration includes section to use the baseline.';
            }
        } else {
            $result['error'] = $runResult->errorOutput ?: $runResult->output;
        }

        return json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
    }

    private function isBaselineImported(?string $configFile, string $baselineFile): bool
    {
        if (null === $configFile || !file_exists($configFile)) {
            return false;
        }

        $content = file_get_contents($configFile);
        if (false === $content) {
            return false;
        }

        return str_contains($content, basename($baselineFile));
    }
}
