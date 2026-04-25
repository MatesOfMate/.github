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
use MatesOfMate\Benchmark\Scenario\ScenarioValidator;
use PHPUnit\Framework\TestCase;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioValidatorTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__.'/../../schema/scenario.schema.json';

    public function testValidScenarioPassesValidation(): void
    {
        $loader = new ScenarioLoader();
        $validator = new ScenarioValidator(self::SCHEMA_PATH);

        $data = $loader->load(__DIR__.'/../Fixtures/scenarios/bug-finding/bug.example.yaml');

        $this->assertSame([], $validator->validate($data));
    }

    public function testRealScenarioFromScenariosDirectoryIsValid(): void
    {
        $loader = new ScenarioLoader();
        $validator = new ScenarioValidator(self::SCHEMA_PATH);

        $data = $loader->load(__DIR__.'/../../scenarios/bug-finding/bug.autowiring.private-service.yaml');

        $this->assertSame([], $validator->validate($data));
    }

    public function testMissingIdFailsValidationWithReadableError(): void
    {
        $loader = new ScenarioLoader();
        $validator = new ScenarioValidator(self::SCHEMA_PATH);

        $data = $loader->load(__DIR__.'/../Fixtures/invalid/missing-id.yaml');
        $errors = $validator->validate($data);

        $this->assertNotSame([], $errors);
        $combined = implode("\n", $errors);
        $this->assertStringContainsString('id', $combined);
    }

    public function testIdPatternIsEnforced(): void
    {
        $loader = new ScenarioLoader();
        $validator = new ScenarioValidator(self::SCHEMA_PATH);

        $data = $loader->load(__DIR__.'/../Fixtures/invalid/bad-id.yaml');
        $errors = $validator->validate($data);

        $this->assertNotSame([], $errors);
        $combined = implode("\n", $errors);
        $this->assertStringContainsString('id', $combined);
    }

    public function testThrowsWhenSchemaFileMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ScenarioValidator(__DIR__.'/does-not-exist.json');
    }
}
