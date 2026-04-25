<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Tests\Command;

use MatesOfMate\Benchmark\Command\BenchmarkRunCommand;
use MatesOfMate\Benchmark\Scenario\ScenarioLoader;
use MatesOfMate\Benchmark\Scenario\ScenarioRepository;
use MatesOfMate\Benchmark\Scenario\ScenarioValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class BenchmarkRunCommandTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__.'/../../schema/scenario.schema.json';
    private const SCENARIOS_DIR = __DIR__.'/../Fixtures/scenarios';

    public function testCommandIsRegistered(): void
    {
        $application = new Application();
        $application->addCommand($this->createCommand());

        $this->assertTrue($application->has('benchmark:run'));
    }

    public function testHasAllRequiredOptions(): void
    {
        $command = $this->createCommand();
        $definition = $command->getDefinition();

        $expected = ['scenario', 'suite', 'adapter', 'model', 'mate', 'output', 'repeat', 'keep-workspace'];
        foreach ($expected as $option) {
            $this->assertTrue($definition->hasOption($option), \sprintf('Option --%s is missing.', $option));
        }
    }

    public function testRunsWithoutAiAndListsScenarios(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('bug.example', $output);
        $this->assertStringContainsString('code.minimal', $output);
        $this->assertStringContainsString('2 scenario(s) loaded', $output);
    }

    public function testFiltersBySuite(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--suite' => 'code-generation']);

        $this->assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('code.minimal', $output);
        $this->assertStringNotContainsString('bug.example', $output);
    }

    public function testFiltersByScenarioId(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--scenario' => 'bug.example']);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('bug.example', $tester->getDisplay());
    }

    public function testUnknownScenarioReturnsInvalid(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--scenario' => 'does.not.exist']);

        $this->assertSame(Command::INVALID, $exit);
    }

    public function testInvalidAdapterReturnsInvalid(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute(['--adapter' => 'unknown']);

        $this->assertSame(Command::INVALID, $exit);
    }

    public function testScenarioAndSuiteAreMutuallyExclusive(): void
    {
        $tester = new CommandTester($this->createCommand());
        $exit = $tester->execute([
            '--scenario' => 'bug.example',
            '--suite' => 'bug-finding',
        ]);

        $this->assertSame(Command::INVALID, $exit);
    }

    private function createCommand(): BenchmarkRunCommand
    {
        $repository = new ScenarioRepository(
            self::SCENARIOS_DIR,
            new ScenarioLoader(),
            new ScenarioValidator(self::SCHEMA_PATH),
        );

        return new BenchmarkRunCommand($repository);
    }
}
