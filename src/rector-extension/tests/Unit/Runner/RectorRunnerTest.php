<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Runner;

use MatesOfMate\RectorExtension\Discovery\ExecutionStrategy;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class RectorRunnerTest extends TestCase
{
    public function testBuildPreviewCommandAlwaysIncludesDryRun(): void
    {
        $strategy = new ExecutionStrategy('local-binary', [\PHP_BINARY, 'vendor/bin/rector']);
        $runner = new RectorRunner('/project');

        $command = $runner->buildCommand($strategy, '/project/rector.php', 'src/Foo.php', true, true, true);

        $this->assertSame([
            \PHP_BINARY,
            'vendor/bin/rector',
            'process',
            '--config',
            '/project/rector.php',
            '--dry-run',
            '--no-progress-bar',
            '--output-format=json',
            '--debug',
            '--rules-summary',
            'src/Foo.php',
        ], $command);
    }

    public function testBuildApplyCommandOmitsDryRun(): void
    {
        $strategy = new ExecutionStrategy('custom-command', ['docker', 'compose', 'exec', 'php', 'vendor/bin/rector']);
        $runner = new RectorRunner('/project');

        $command = $runner->buildCommand($strategy, '/project/rector.php', null, false, false, false);

        $this->assertSame([
            'docker',
            'compose',
            'exec',
            'php',
            'vendor/bin/rector',
            'process',
            '--config',
            '/project/rector.php',
            '--no-progress-bar',
            '--output-format=json',
        ], $command);
    }
}
