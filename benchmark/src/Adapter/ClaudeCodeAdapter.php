<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter;

use MatesOfMate\Benchmark\Adapter\Platform\PlatformAdapter;
use Symfony\AI\Platform\Bridge\ClaudeCode\Factory as ClaudeCodeFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Drives the Claude Code CLI via the `symfony/ai-claude-code-platform` bridge.
 *
 * The bridge owns subprocess management, stream-json parsing and
 * `--mcp-config` plumbing; this adapter only marshals benchmark inputs into
 * `Platform::invoke()` arguments. Override the binary with
 * `BENCHMARK_CLAUDE_BIN`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ClaudeCodeAdapter extends PlatformAdapter
{
    public const NAME = 'claude';
    public const DEFAULT_MODEL = 'sonnet';
    public const ENV_BINARY = 'BENCHMARK_CLAUDE_BIN';

    /**
     * @param (\Closure(float): PlatformInterface)|null $platformFactory
     */
    public function __construct(PlatformInterface $platform, string $defaultModel = self::DEFAULT_MODEL, private readonly ?\Closure $platformFactory = null)
    {
        parent::__construct($platform, $defaultModel);
    }

    public static function withDefaults(): self
    {
        $binary = getenv(self::ENV_BINARY);
        $cliBinary = false === $binary || '' === $binary ? null : $binary;

        $factory = static fn (float $timeout): PlatformInterface => ClaudeCodeFactory::createPlatform(
            cliBinary: $cliBinary,
            timeout: $timeout,
        );

        // The concrete platform is rebuilt per run so the scenario's
        // task.timeout_seconds actually bounds the CLI subprocess.
        return new self($factory(300.0), platformFactory: $factory);
    }

    public function name(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function run(AssistantRunInput $input): AssistantRunResult
    {
        if ($this->platformFactory instanceof \Closure) {
            $platform = ($this->platformFactory)((float) max($input->timeoutSeconds, 60));

            return (new self($platform, $this->defaultModel))->run($input);
        }

        $authFailure = $this->preflightAuthenticationCheck();
        if ($authFailure instanceof AssistantRunResult) {
            return $authFailure;
        }

        return parent::run($input);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function buildOptions(AssistantRunInput $input): array
    {
        $options = parent::buildOptions($input);
        // Each scenario runs in an isolated workspace under var/benchmark/runs/,
        // so it is safe — and necessary for non-interactive runs — to bypass the
        // permission gate. Without this, Claude only describes the fix instead
        // of applying it.
        $options['permission_mode'] = 'bypassPermissions';
        // Without this, Claude persists each benchmark run as a resumable
        // session and the next run's reasoning gets contaminated by stale
        // context from the previous one (we observed thinking blocks
        // referencing a sibling run's workspace mid-prompt). Each scenario
        // attempt must start from a clean slate.
        $options['no_session_persistence'] = true;
        // Only the benchmark-provisioned MCP servers may be visible; without
        // this the developer's personal MCP configuration leaks into every
        // run and contaminates tool-usage metrics.
        $options['strict_mcp_config'] = true;

        return $options;
    }

    private function preflightAuthenticationCheck(): ?AssistantRunResult
    {
        $binary = $this->resolveBinary();
        if (null === $binary) {
            return null;
        }

        $process = new Process([$binary, 'auth', 'status']);
        $process->run();

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();

        if ($process->isSuccessful()) {
            return null;
        }

        $status = json_decode($stdout, true);
        if (\is_array($status) && false === ($status['loggedIn'] ?? true)) {
            return AssistantRunResult::failure(
                errorMessage: 'Claude CLI is not authenticated. Run `claude auth login` before benchmarking.',
                exitCode: $process->getExitCode() ?? -1,
                stdout: $stdout,
                stderr: $stderr,
            );
        }

        return null;
    }

    private function resolveBinary(): ?string
    {
        $binary = getenv(self::ENV_BINARY);
        if (false !== $binary && '' !== $binary) {
            return $binary;
        }

        return (new ExecutableFinder())->find('claude');
    }
}
