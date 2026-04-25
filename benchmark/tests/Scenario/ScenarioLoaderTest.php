<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Scenario;

use MatesOfMate\Benchmark\Scenario\ScenarioLoader;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioLoaderTest extends TestCase
{
    public function testLoadsValidYamlIntoArray(): void
    {
        $loader = new ScenarioLoader();
        $data = $loader->load(__DIR__.'/../Fixtures/scenarios/code-generation/code.minimal.yaml');

        $this->assertSame('code.minimal', $data['id']);
        $this->assertSame('code-generation', $data['suite']);
        $this->assertSame('easy', $data['difficulty']);
        $this->assertIsArray($data['fixture']);
        $this->assertIsArray($data['task']);
    }

    public function testThrowsWhenFileDoesNotExist(): void
    {
        $loader = new ScenarioLoader();

        $this->expectException(\InvalidArgumentException::class);
        $loader->load(__DIR__.'/../Fixtures/scenarios/does-not-exist.yaml');
    }

    public function testThrowsWhenRootIsNotAMapping(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bench-loader-');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, "- 1\n- 2\n");

        try {
            $loader = new ScenarioLoader();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/YAML mapping/');
            $loader->load($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
