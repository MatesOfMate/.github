<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Evaluator;

/**
 * Checks that the assistant verified its own work by actually executing the
 * scenario's verification commands (or at least some test/analysis command)
 * during the run.
 *
 * Evidence comes from captured tool calls: the command arguments of shell
 * tool invocations are searched for the scenario's `pass_commands` and for
 * generic test runners. Merely *mentioning* a tool in the final response text
 * earns nothing — talk is cheap, execution is evidence.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class VerificationEvaluator implements EvaluatorInterface
{
    public const NAME = 'verification';

    private const string GENERIC_TEST_PATTERN = '/(?:phpunit|\bpest\b|phpstan|psalm|pytest|composer\s+test|npm\s+test|yarn\s+test|php\s+\S*test\S*)/i';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $assistant = $input->outcome->assistantResult;

        if (!$assistant instanceof \MatesOfMate\Benchmark\Adapter\AssistantRunResult) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'Adapter did not produce a result; verification cannot be assessed.',
                evidence: ['executed_pass_commands' => [], 'generic_test_evidence' => false],
            );
        }

        $corpus = $this->buildToolCorpus($assistant->toolCalls);
        $passCommands = $this->passCommands($input);

        $executed = [];
        $missing = [];

        foreach ($passCommands as $command) {
            if ('' !== $corpus && str_contains($this->normalize($corpus), $this->normalize($command))) {
                $executed[] = $command;
            } else {
                $missing[] = $command;
            }
        }

        if ([] !== $passCommands && [] !== $executed) {
            $ratio = \count($executed) / \count($passCommands);

            return new EvaluationResult(
                name: self::NAME,
                score: round($ratio * EvaluationResult::MAX_SCORE, 2),
                passed: [] === $missing,
                explanation: \sprintf('Assistant executed %d/%d verification command(s) itself.', \count($executed), \count($passCommands)),
                evidence: [
                    'executed_pass_commands' => $executed,
                    'missing_pass_commands' => $missing,
                    'generic_test_evidence' => true,
                ],
            );
        }

        if ('' !== $corpus && 1 === preg_match(self::GENERIC_TEST_PATTERN, $corpus)) {
            return new EvaluationResult(
                name: self::NAME,
                score: 2.5,
                passed: false,
                explanation: 'Assistant ran a test/analysis command, but not the scenario verification command.',
                evidence: [
                    'executed_pass_commands' => [],
                    'missing_pass_commands' => $missing,
                    'generic_test_evidence' => true,
                ],
            );
        }

        return new EvaluationResult(
            name: self::NAME,
            score: 0.0,
            passed: false,
            explanation: 'No evidence that the assistant executed any verification command.',
            evidence: [
                'executed_pass_commands' => [],
                'missing_pass_commands' => $missing,
                'generic_test_evidence' => false,
            ],
        );
    }

    /**
     * @param list<\MatesOfMate\Benchmark\Adapter\ToolCall> $toolCalls
     */
    private function buildToolCorpus(array $toolCalls): string
    {
        $chunks = [];

        foreach ($toolCalls as $call) {
            $chunks[] = $call->name;

            foreach ($call->arguments as $value) {
                if (\is_scalar($value)) {
                    $chunks[] = (string) $value;
                } elseif (\is_array($value)) {
                    $chunks[] = implode(' ', array_filter($value, is_scalar(...)));
                }
            }
        }

        return implode("\n", $chunks);
    }

    /**
     * @return list<string>
     */
    private function passCommands(EvaluationInput $input): array
    {
        $raw = $input->scenario->expected['pass_commands'] ?? [];
        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($command): string => \is_string($command) ? trim($command) : '', $raw),
            static fn (string $command): bool => '' !== $command,
        ));
    }

    private function normalize(string $text): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', $text));
    }
}
