<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Workflow;

use MatesOfMate\RectorExtension\Discovery\ExecutionStrategy;
use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use MatesOfMate\RectorExtension\Parser\ParsedRectorResult;
use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use MatesOfMate\RectorExtension\Validation\PathValidator;

/**
 * Shared preview and apply workflow behind the Rector tools.
 *
 * @internal
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class RectorWorkflow
{
    public function __construct(
        private readonly RectorDiscovery $discovery,
        private readonly PathValidator $pathValidator,
        private readonly RectorRunner $runner,
        private readonly RectorOutputParser $parser,
        private readonly ToonFormatter $formatter,
    ) {
    }

    public function run(
        bool $preview,
        ?string $path,
        ?string $configuration,
        bool $debug,
        bool $rulesSummary,
        string $mode,
    ): string {
        $context = $this->discovery->inspect();
        $strategy = $context->preferredStrategy;

        if (!$strategy instanceof ExecutionStrategy) {
            throw new \RuntimeException('Rector is not available. Install project-local Rector or configure matesofmate_rector.custom_command.');
        }

        try {
            $config = $this->resolveConfiguration($configuration, $context->configuration);
            $validatedPath = $this->pathValidator->validate($path);
        } catch (\InvalidArgumentException $exception) {
            return $this->formatter->format(
                ParsedRectorResult::validationFailure(
                    $preview,
                    [$exception->getMessage()],
                    $this->rejectedInput($path, $configuration),
                ),
                $mode,
            );
        }

        $runResult = $preview
            ? $this->runner->preview($strategy, $config, $validatedPath, $debug, $rulesSummary)
            : $this->runner->apply($strategy, $config, $validatedPath, $debug, $rulesSummary);

        return $this->formatter->format($this->parser->parse($runResult, $preview), $mode);
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
        if (null !== $configuration && '' !== $configuration) {
            // Validated paths are project-root relative, which is what Rector is run with.
            $validated = $this->pathValidator->validate($configuration);

            if (null !== $validated) {
                return $validated;
            }
        }

        if (null === $detectedConfiguration) {
            throw new \RuntimeException('Rector configuration was not found. Add rector.php or pass a valid configuration path; this extension will not generate it.');
        }

        return $detectedConfiguration;
    }
}
