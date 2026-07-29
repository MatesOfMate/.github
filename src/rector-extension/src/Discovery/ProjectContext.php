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
 * Project-level Rector discovery result.
 *
 * @internal
 *
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class ProjectContext
{
    /**
     * @param array<string, string|array<int, string>> $composerScripts
     * @param array<int, string>                       $diagnostics
     */
    public function __construct(
        public readonly string $projectRoot,
        public readonly bool $rectorInstalled,
        public readonly ?string $localBinary,
        public readonly ?string $configuration,
        public readonly array $composerScripts,
        public readonly ?ExecutionStrategy $preferredStrategy,
        public readonly string $phpBinary,
        public readonly array $diagnostics,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $strategy = null;
        if (isset($data['preferredStrategy']) && \is_array($data['preferredStrategy'])) {
            $strategy = new ExecutionStrategy(
                (string) $data['preferredStrategy']['type'],
                $data['preferredStrategy']['command'],
            );
        }

        return new self(
            projectRoot: (string) $data['projectRoot'],
            rectorInstalled: (bool) $data['rectorInstalled'],
            localBinary: isset($data['localBinary']) ? (string) $data['localBinary'] : null,
            configuration: isset($data['configuration']) ? (string) $data['configuration'] : null,
            composerScripts: $data['composerScripts'] ?? [],
            preferredStrategy: $strategy,
            phpBinary: $data['phpBinary'] ?? \PHP_BINARY,
            diagnostics: $data['diagnostics'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'project_root' => $this->projectRoot,
            'rector_installed' => $this->rectorInstalled,
            'local_binary' => $this->localBinary,
            'configuration' => $this->configuration,
            'composer_scripts' => $this->composerScripts,
            'preferred_strategy' => $this->preferredStrategy?->toArray(),
            'php_binary' => $this->phpBinary,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
