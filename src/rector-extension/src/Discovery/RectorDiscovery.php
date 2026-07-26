<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Discovery;

/**
 * Discovers Rector configuration and execution strategy for a project.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RectorDiscovery
{
    private const CONFIG_FILES = [
        'rector.php',
        'rector.php.dist',
    ];

    /**
     * @param array<int, string> $customCommand
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly array $customCommand = [],
    ) {
    }

    public function inspect(): ProjectContext
    {
        $projectRoot = $this->normalizeRoot($this->projectRoot);
        $localBinary = $this->detectLocalBinary($projectRoot);
        $configuration = $this->detectConfiguration($projectRoot);
        $composerScripts = $this->detectComposerScripts($projectRoot);
        $preferredStrategy = $this->detectPreferredStrategy($localBinary);
        $rectorInstalled = $preferredStrategy instanceof ExecutionStrategy;
        $diagnostics = [];

        if (!$rectorInstalled) {
            $diagnostics[] = 'Install Rector in the project or configure matesofmate_rector.custom_command.';
        }

        if (null === $configuration) {
            $diagnostics[] = 'Add rector.php or pass a valid configuration path. The extension will not generate it.';
        }

        return new ProjectContext(
            projectRoot: $projectRoot,
            rectorInstalled: $rectorInstalled,
            localBinary: $localBinary,
            configuration: $configuration,
            composerScripts: $composerScripts,
            preferredStrategy: $preferredStrategy,
            phpBinary: \PHP_BINARY,
            diagnostics: $diagnostics,
        );
    }

    private function normalizeRoot(string $projectRoot): string
    {
        $realPath = realpath($projectRoot);

        return false === $realPath ? rtrim($projectRoot, '/') : $realPath;
    }

    private function detectLocalBinary(string $projectRoot): ?string
    {
        $binary = $projectRoot.'/vendor/bin/rector';

        return file_exists($binary) ? $binary : null;
    }

    private function detectConfiguration(string $projectRoot): ?string
    {
        foreach (self::CONFIG_FILES as $configFile) {
            $path = $projectRoot.'/'.$configFile;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function detectComposerScripts(string $projectRoot): array
    {
        $composerFile = $projectRoot.'/composer.json';
        if (!file_exists($composerFile)) {
            return [];
        }

        $content = file_get_contents($composerFile);
        if (false === $content) {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($data) || !isset($data['scripts']) || !\is_array($data['scripts'])) {
            return [];
        }

        $scripts = [];
        foreach ($data['scripts'] as $name => $script) {
            $serialized = \is_array($script) ? implode("\n", array_map(strval(...), $script)) : (string) $script;
            if (str_contains(strtolower($name."\n".$serialized), 'rector')) {
                $scripts[(string) $name] = $script;
            }
        }

        return $scripts;
    }

    private function detectPreferredStrategy(?string $localBinary): ?ExecutionStrategy
    {
        if ([] !== $this->customCommand) {
            return new ExecutionStrategy('custom-command', $this->customCommand);
        }

        if (null !== $localBinary) {
            return new ExecutionStrategy('local-binary', [\PHP_BINARY, $localBinary]);
        }

        return null;
    }
}
