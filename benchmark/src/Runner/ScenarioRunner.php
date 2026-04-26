<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Runner;

use MatesOfMate\Benchmark\Adapter\AssistantRunInput;
use MatesOfMate\Benchmark\Adapter\AssistantRunResult;
use MatesOfMate\Benchmark\Mate\MateConfiguration;
use MatesOfMate\Benchmark\Mate\MateConfigurationFactory;
use MatesOfMate\Benchmark\Mate\MateMetrics;
use MatesOfMate\Benchmark\Mate\MateMetricsCollector;
use MatesOfMate\Benchmark\Runner\Exception\CommandFailedException;
use MatesOfMate\Benchmark\Runner\Exception\FixtureNotFoundException;
use MatesOfMate\Benchmark\Scenario\Scenario;

/**
 * Runs a single scenario attempt: copy fixture, run setup, seal baseline, invoke adapter, collect diff and verify.
 *
 * Adapter exceptions are caught and converted to {@see RunOutcome} entries with status
 * {@see RunStatus::AdapterError}, so a failed run never crashes the benchmark loop.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioRunner
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly WorkspaceFactory $workspaceFactory,
        private readonly FixtureCopier $fixtureCopier,
        private readonly CommandExecutor $commandExecutor,
        private readonly GitDiffCollector $diffCollector,
        private readonly MateConfigurationFactory $mateConfigurationFactory,
        private readonly MateMetricsCollector $mateMetricsCollector,
    ) {
    }

    public function run(RunRequest $request): RunOutcome
    {
        $totalStart = microtime(true);
        $scenario = $request->scenario;

        $workspace = $this->workspaceFactory->create(
            runId: $request->runId,
            scenarioId: $scenario->id,
            attempt: $request->attempt,
            keep: $request->keepWorkspace,
        );

        $setupResults = [];
        $baselineResults = [];
        $assistantResult = null;
        $diff = null;
        $verificationResults = [];
        $mateMetrics = MateMetrics::disabled();
        $status = RunStatus::Failed;
        $errorMessage = null;
        $mateConfig = MateConfiguration::disabled();

        try {
            $this->copyFixture($scenario, $workspace);

            $this->diffCollector->initialize($workspace);

            $setupResults = $this->runCommands($scenario->fixture['setup'] ?? [], $workspace, mustSucceed: true, stage: 'setup');
            $baselineResults = $this->runCommands($scenario->fixture['baseline'] ?? [], $workspace, mustSucceed: false, stage: 'baseline');

            // Provision Mate config before sealing so it becomes part of the
            // starting workspace state, not part of the AI-attributed diff.
            $mateConfig = $this->mateConfigurationFactory->create($workspace, $scenario, $request->mateEnabled);

            $this->diffCollector->seal($workspace);

            $assistantResult = $this->invokeAdapter($request, $workspace, $mateConfig);

            $diff = $this->diffCollector->collect($workspace);

            $verificationResults = $this->runCommands($scenario->expected['pass_commands'] ?? [], $workspace, mustSucceed: false, stage: 'verify');

            $mateMetrics = $this->mateMetricsCollector->collect($assistantResult, $mateConfig);

            $status = $this->classify($assistantResult, $verificationResults);
        } catch (CommandFailedException $exception) {
            $status = RunStatus::SetupError;
            $errorMessage = $exception->getMessage();
            $setupResults[] = $exception->result;
        } catch (FixtureNotFoundException $exception) {
            $status = RunStatus::SetupError;
            $errorMessage = $exception->getMessage();
        } finally {
            $this->workspaceFactory->destroy($workspace);
        }

        return new RunOutcome(
            scenario: $scenario,
            workspace: $workspace,
            status: $status,
            setupResults: $setupResults,
            baselineResults: $baselineResults,
            assistantResult: $assistantResult,
            diff: $diff,
            verificationResults: $verificationResults,
            mateMetrics: $mateMetrics,
            totalDurationMs: (microtime(true) - $totalStart) * 1000.0,
            errorMessage: $errorMessage,
        );
    }

    private function copyFixture(Scenario $scenario, Workspace $workspace): void
    {
        $fixturePath = $scenario->fixture['path'] ?? null;
        if (!\is_string($fixturePath) || '' === $fixturePath) {
            return;
        }

        $absolute = $this->resolveFixturePath($fixturePath);
        $this->fixtureCopier->copy($absolute, $workspace);
    }

    private function resolveFixturePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($this->projectRoot, '/').'/'.ltrim($path, '/');
    }

    /**
     * @param list<string> $commands
     *
     * @return list<CommandResult>
     */
    private function runCommands(array $commands, Workspace $workspace, bool $mustSucceed, string $stage): array
    {
        $results = [];

        foreach ($commands as $command) {
            if (!\is_string($command) || '' === trim($command)) {
                continue;
            }

            $results[] = $mustSucceed
                ? $this->commandExecutor->mustExecute($command, $workspace->path, $stage)
                : $this->commandExecutor->execute($command, $workspace->path);
        }

        return $results;
    }

    private function invokeAdapter(RunRequest $request, Workspace $workspace, MateConfiguration $mateConfig): AssistantRunResult
    {
        $prompt = (string) ($request->scenario->task['prompt'] ?? '');
        $timeout = (int) ($request->scenario->task['timeout_seconds'] ?? 600);

        $input = new AssistantRunInput(
            workspacePath: $workspace->path,
            prompt: $prompt,
            model: $request->model,
            mateConfig: $mateConfig,
            env: $mateConfig->env,
            timeoutSeconds: $timeout > 0 ? $timeout : 600,
        );

        try {
            return $request->adapter->run($input);
        } catch (\Throwable $exception) {
            return AssistantRunResult::failure(
                errorMessage: $exception->getMessage(),
            );
        }
    }

    /**
     * @param list<CommandResult> $verificationResults
     */
    private function classify(AssistantRunResult $assistantResult, array $verificationResults): RunStatus
    {
        if (!$assistantResult->successful) {
            return RunStatus::AdapterError;
        }

        foreach ($verificationResults as $result) {
            if (!$result->successful()) {
                return RunStatus::Failed;
            }
        }

        return RunStatus::Passed;
    }
}
