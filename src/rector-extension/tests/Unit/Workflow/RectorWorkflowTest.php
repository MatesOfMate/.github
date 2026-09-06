<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Tests\Unit\Workflow;

use MatesOfMate\RectorExtension\Cache\RunCache;
use MatesOfMate\RectorExtension\Discovery\ProjectContext;
use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use MatesOfMate\RectorExtension\Grouping\RuleGrouper;
use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use MatesOfMate\RectorExtension\Runner\RunResult;
use MatesOfMate\RectorExtension\Validation\PathValidator;
use MatesOfMate\RectorExtension\Workflow\RectorWorkflow;
use PHPUnit\Framework\TestCase;

/**
 * @author mdjdev <18183474+mdjdev@users.noreply.github.com>
 */
class RectorWorkflowTest extends TestCase
{
    public function testRunReturnsStructuredFailureForInvalidPath(): void
    {
        $validator = $this->createMock(PathValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with('../README.md')
            ->willThrowException(new \InvalidArgumentException('Path must be inside the project root: ../README.md'));

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->never())->method('preview');

        $workflow = new RectorWorkflow(
            $this->discoveryReturning($this->context()),
            $validator,
            $runner,
            $this->createMock(RectorOutputParser::class),
            new ToonFormatter(),
            new RuleGrouper(),
            $this->createMock(RunCache::class),
        );

        $payload = json_decode($workflow->run(true, '../README.md', null, false, false, 'default'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame('preview', $payload['workflow']);
        $this->assertSame('FAILED', $payload['status']);
        $this->assertSame(1, $payload['exit_code']);
        $this->assertSame(['Path must be inside the project root: ../README.md'], $payload['diagnostics']);
        $this->assertSame(['path' => '../README.md'], $payload['rejected_input']);
    }

    public function testRunRefusesMissingConfigurationBeforeRunningRector(): void
    {
        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->never())->method('preview');

        $workflow = new RectorWorkflow(
            $this->discoveryReturning($this->context(configuration: null)),
            $this->createMock(PathValidator::class),
            $runner,
            $this->createMock(RectorOutputParser::class),
            $this->createMock(ToonFormatter::class),
            new RuleGrouper(),
            $this->createMock(RunCache::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rector configuration was not found.');

        $workflow->run(true, null, null, false, false, 'default');
    }

    public function testRunRefusesWhenRectorIsNotAvailable(): void
    {
        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->never())->method('preview');

        $workflow = new RectorWorkflow(
            $this->discoveryReturning($this->context(strategy: null)),
            $this->createMock(PathValidator::class),
            $runner,
            $this->createMock(RectorOutputParser::class),
            $this->createMock(ToonFormatter::class),
            new RuleGrouper(),
            $this->createMock(RunCache::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rector is not available.');

        $workflow->run(true, null, null, false, false, 'default');
    }

    public function testPreviewRunUsesTheDryRunRunnerEntryPoint(): void
    {
        $context = $this->context();

        $validator = $this->createMock(PathValidator::class);
        $validator->method('validate')->with('src')->willReturn('src');

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->once())
            ->method('preview')
            ->with($context->preferredStrategy, '/project/rector.php', 'src', false, false)
            ->willReturn($this->runResult());
        $runner->expects($this->never())->method('apply');

        $workflow = new RectorWorkflow(
            $this->discoveryReturning($context),
            $validator,
            $runner,
            new RectorOutputParser(),
            new ToonFormatter(),
            new RuleGrouper(),
            $this->createMock(RunCache::class),
        );

        $payload = json_decode($workflow->run(true, 'src', null, false, false, 'summary'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame('preview', $payload['workflow']);
    }

    public function testApplyRunUsesTheWriteRunnerEntryPoint(): void
    {
        $context = $this->context();

        $validator = $this->createMock(PathValidator::class);
        $validator->method('validate')->with('src')->willReturn('src');

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->once())
            ->method('apply')
            ->with($context->preferredStrategy, '/project/rector.php', 'src', false, false)
            ->willReturn($this->runResult());
        $runner->expects($this->never())->method('preview');

        $workflow = new RectorWorkflow(
            $this->discoveryReturning($context),
            $validator,
            $runner,
            new RectorOutputParser(),
            new ToonFormatter(),
            new RuleGrouper(),
            $this->createMock(RunCache::class),
        );

        $payload = json_decode($workflow->run(false, 'src', null, false, false, 'summary'), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame('apply', $payload['workflow']);
    }

    public function testExplicitConfigurationOverridesTheDetectedOne(): void
    {
        $context = $this->context();

        $validator = $this->createMock(PathValidator::class);
        $validator->method('validate')->willReturnMap([
            ['rector-ci.php', 'rector-ci.php'],
            [null, null],
        ]);

        $runner = $this->createMock(RectorRunner::class);
        $runner->expects($this->once())
            ->method('preview')
            ->with($context->preferredStrategy, 'rector-ci.php', null, false, false)
            ->willReturn($this->runResult());

        $workflow = new RectorWorkflow(
            $this->discoveryReturning($context),
            $validator,
            $runner,
            new RectorOutputParser(),
            new ToonFormatter(),
            new RuleGrouper(),
            $this->createMock(RunCache::class),
        );

        $workflow->run(true, null, 'rector-ci.php', false, false, 'summary');
    }

    private function discoveryReturning(ProjectContext $context): RectorDiscovery
    {
        $discovery = $this->createMock(RectorDiscovery::class);
        $discovery->method('inspect')->willReturn($context);

        return $discovery;
    }

    /**
     * @param array{type: string, command: array<int, string>}|null $strategy
     */
    private function context(?string $configuration = '/project/rector.php', ?array $strategy = ['type' => 'local-binary', 'command' => ['php', '/project/vendor/bin/rector']]): ProjectContext
    {
        return ProjectContext::fromArray([
            'projectRoot' => '/project',
            'rectorInstalled' => null !== $strategy,
            'configuration' => $configuration,
            'preferredStrategy' => $strategy,
        ]);
    }

    private function runResult(): RunResult
    {
        return new RunResult(
            command: ['php', '/project/vendor/bin/rector', 'process'],
            strategy: 'local-binary',
            workingDirectory: '/project',
            exitCode: 0,
            output: '{"totals":{"changed_files":0,"errors":0}}',
            errorOutput: '',
            timedOut: false,
        );
    }
}
