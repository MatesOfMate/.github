<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Discovery;

use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class RectorDiscoveryTest extends TestCase
{
    public function testInspectDetectsLocalBinaryConfigAndComposerScripts(): void
    {
        $projectRoot = $this->createProject([
            'vendor/bin/rector' => '#!/usr/bin/env php',
            'rector.php' => '<?php return [];',
            'composer.json' => json_encode([
                'scripts' => [
                    'rector' => 'vendor/bin/rector process --dry-run',
                    'lint' => [
                        'vendor/bin/phpstan analyse',
                        'vendor/bin/rector process --dry-run',
                    ],
                    'test' => 'vendor/bin/phpunit',
                ],
            ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        ]);

        $context = (new RectorDiscovery($projectRoot))->inspect();

        $this->assertTrue($context->rectorInstalled);
        $this->assertSame($projectRoot.'/vendor/bin/rector', $context->localBinary);
        $this->assertSame($projectRoot.'/rector.php', $context->configuration);
        $this->assertNotNull($context->preferredStrategy);
        $this->assertSame('local-binary', $context->preferredStrategy->type);
        $this->assertSame(['rector', 'lint'], array_keys($context->composerScripts));
    }

    public function testCustomCommandHasPriorityOverLocalBinary(): void
    {
        $projectRoot = $this->createProject([
            'vendor/bin/rector' => '#!/usr/bin/env php',
            'rector.php' => '<?php return [];',
        ]);

        $context = (new RectorDiscovery($projectRoot, ['docker', 'compose', 'exec', 'php', 'vendor/bin/rector']))->inspect();

        $this->assertNotNull($context->preferredStrategy);
        $this->assertSame('custom-command', $context->preferredStrategy->type);
        $this->assertSame(['docker', 'compose', 'exec', 'php', 'vendor/bin/rector'], $context->preferredStrategy->command);
    }

    public function testMissingRectorAndConfigReturnActionableDiagnostics(): void
    {
        $projectRoot = $this->createProject([
            'composer.json' => json_encode(['scripts' => []], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        ]);

        $context = (new RectorDiscovery($projectRoot))->inspect();

        $this->assertFalse($context->rectorInstalled);
        $this->assertNull($context->configuration);
        $this->assertNull($context->preferredStrategy);
        $this->assertContains('Install Rector in the project or configure matesofmate_rector.custom_command.', $context->diagnostics);
        $this->assertContains('Add rector.php or pass a valid configuration path. The extension will not generate it.', $context->diagnostics);
    }

    /**
     * @param array<string, string> $files
     */
    private function createProject(array $files): string
    {
        $projectRoot = sys_get_temp_dir().'/rector_discovery_test_'.bin2hex(random_bytes(4));
        mkdir($projectRoot, 0777, true);

        foreach ($files as $file => $content) {
            $path = $projectRoot.'/'.$file;
            $directory = \dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $content);
        }

        $realPath = realpath($projectRoot);
        $this->assertIsString($realPath);

        return $realPath;
    }
}
