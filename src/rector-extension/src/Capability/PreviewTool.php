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
use MatesOfMate\RectorExtension\Parser\ParsedRectorResult;
use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use MatesOfMate\RectorExtension\Validation\PathValidator;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Runs Rector in mandatory dry-run mode and returns structured preview output.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class PreviewTool
{
    public function __construct(
        private readonly RectorDiscovery $discovery,
        private readonly PathValidator $pathValidator,
        private readonly RectorRunner $runner,
        private readonly RectorOutputParser $parser,
        private readonly ToonFormatter $formatter,
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
        name: 'rector-preview',
        title: 'Rector Preview',
        description: 'Preview Rector refactors with --dry-run. This tool never applies source-code changes.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function execute(
        ?string $path = null,
        ?string $configuration = null,
        bool $debug = false,
        bool $rulesSummary = false,
        #[Schema(description: 'Output detail level', enum: ['default', 'summary', 'detailed'])]
        string $mode = 'default',
    ): string {
        $context = $this->discovery->inspect();
        $strategy = $context->preferredStrategy;
        if (!$strategy instanceof \MatesOfMate\RectorExtension\Discovery\ExecutionStrategy) {
            throw new \RuntimeException('Rector is not available. Install project-local Rector or configure matesofmate_rector.custom_command.');
        }

        try {
            $config = $this->resolveConfiguration($configuration, $context->configuration);
            $validatedPath = $this->pathValidator->validate($path);
        } catch (\InvalidArgumentException $exception) {
            return $this->formatValidationFailure(
                $exception->getMessage(),
                $this->rejectedInput($path, $configuration),
                $mode,
            );
        }

        $runResult = $this->runner->preview($strategy, $config, $validatedPath, $debug, $rulesSummary);
        $parsedResult = $this->parser->parse($runResult, true);

        return $this->formatter->format($parsedResult, $mode);
    }

    /**
     * @param array<string, mixed> $rejectedInput
     */
    private function formatValidationFailure(string $diagnostic, array $rejectedInput, string $mode): string
    {
        return $this->formatter->format(ParsedRectorResult::validationFailure(true, [$diagnostic], $rejectedInput), $mode);
    }

    /**
     * @return array<string, mixed>
     */
    private function rejectedInput(?string $path, ?string $configuration): array
    {
        $input = [];

        if (null !== $path && '' !== $path) {
            $input['path'] = $path;
        }

        if (null !== $configuration && '' !== $configuration) {
            $input['configuration'] = $configuration;
        }

        return $input;
    }

    private function resolveConfiguration(?string $configuration, ?string $detectedConfiguration): string
    {
        $resolved = null !== $configuration && '' !== $configuration
            ? $this->pathValidator->validate($configuration)
            : $detectedConfiguration;

        if (null === $resolved) {
            throw new \RuntimeException('Rector configuration was not found. Add rector.php or pass a valid configuration path; this extension will not generate it.');
        }

        return str_starts_with($resolved, '/') ? $resolved : $this->pathValidator->validate($resolved) ?? $resolved;
    }
}
