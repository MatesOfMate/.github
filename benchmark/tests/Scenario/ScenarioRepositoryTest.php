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

use MatesOfMate\Benchmark\Scenario\Exception\ScenarioValidationException;
use MatesOfMate\Benchmark\Scenario\ScenarioLoader;
use MatesOfMate\Benchmark\Scenario\ScenarioRepository;
use MatesOfMate\Benchmark\Scenario\ScenarioValidator;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioRepositoryTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__.'/../../schema/scenario.schema.json';
    private const VALID_DIR = __DIR__.'/../Fixtures/scenarios';
    private const INVALID_DIR = __DIR__.'/../Fixtures/invalid';

    public function testAllReturnsEverySceneInTheTree(): void
    {
        $repository = $this->createRepository(self::VALID_DIR);
        $scenarios = $repository->all();

        $ids = array_map(static fn ($s) => $s->id, $scenarios);
        sort($ids);

        $this->assertSame(['bug.example', 'code.minimal'], $ids);
    }

    public function testFindReturnsScenarioById(): void
    {
        $repository = $this->createRepository(self::VALID_DIR);

        $scenario = $repository->find('bug.example');
        $this->assertNotNull($scenario);
        $this->assertSame('bug-finding', $scenario->suite);
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        $repository = $this->createRepository(self::VALID_DIR);

        $this->assertNull($repository->find('does.not.exist'));
    }

    public function testGetThrowsForUnknownId(): void
    {
        $repository = $this->createRepository(self::VALID_DIR);

        $this->expectException(\InvalidArgumentException::class);
        $repository->get('does.not.exist');
    }

    public function testBySuiteFiltersByName(): void
    {
        $repository = $this->createRepository(self::VALID_DIR);

        $scenarios = $repository->bySuite('code-generation');

        $this->assertCount(1, $scenarios);
        $this->assertSame('code.minimal', $scenarios[0]->id);
    }

    public function testSuitesReturnsSortedDistinctList(): void
    {
        $repository = $this->createRepository(self::VALID_DIR);

        $this->assertSame(['bug-finding', 'code-generation'], $repository->suites());
    }

    public function testInvalidScenariosThrowValidationException(): void
    {
        $repository = $this->createRepository(self::INVALID_DIR);

        $this->expectException(ScenarioValidationException::class);
        $repository->all();
    }

    public function testMissingDirectoryYieldsEmptyResult(): void
    {
        $repository = $this->createRepository(__DIR__.'/../Fixtures/does-not-exist');

        $this->assertSame([], $repository->all());
        $this->assertSame([], $repository->suites());
    }

    public function testDuplicateIdsAreRejected(): void
    {
        $tmpDir = sys_get_temp_dir().'/bench-repo-'.uniqid();
        mkdir($tmpDir.'/a', 0o777, true);
        mkdir($tmpDir.'/b', 0o777, true);

        $yaml = <<<'YAML'
            id: dup.scenario
            suite: code-generation
            fixture:
              path: fixtures/dup
            task:
              prompt: test
            expected:
              pass_commands:
                - echo
            YAML;

        file_put_contents($tmpDir.'/a/dup.yaml', $yaml);
        file_put_contents($tmpDir.'/b/dup.yaml', $yaml);

        try {
            $repository = $this->createRepository($tmpDir);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/Duplicate scenario id/');
            $repository->all();
        } finally {
            @unlink($tmpDir.'/a/dup.yaml');
            @unlink($tmpDir.'/b/dup.yaml');
            @rmdir($tmpDir.'/a');
            @rmdir($tmpDir.'/b');
            @rmdir($tmpDir);
        }
    }

    private function createRepository(string $directory): ScenarioRepository
    {
        return new ScenarioRepository(
            $directory,
            new ScenarioLoader(),
            new ScenarioValidator(self::SCHEMA_PATH),
        );
    }
}
