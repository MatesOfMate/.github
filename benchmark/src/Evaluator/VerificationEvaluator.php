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
 * Heuristic check that the assistant verified its own work (ran tests, lints, ...).
 *
 * Looks for verification keywords in the assistant stdout and tool-call names.
 * Adapter integrations that surface richer trace data (e.g. shell tool calls)
 * will benefit most; other adapters fall back to the stdout heuristic.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class VerificationEvaluator implements EvaluatorInterface
{
    public const NAME = 'verification';

    /**
     * @var list<string>
     */
    private const VERIFICATION_KEYWORDS = ['phpunit', 'pest', 'phpstan', 'psalm', 'rector', 'composer test', 'npm test', 'pytest', 'yarn test'];

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $assistant = $input->outcome->assistantResult;

        if (null === $assistant) {
            return new EvaluationResult(
                name: self::NAME,
                score: 0.0,
                passed: false,
                explanation: 'Adapter did not produce a result; verification cannot be assessed.',
                evidence: ['matched_keywords' => []],
            );
        }

        $haystack = strtolower($assistant->stdout."\n".$assistant->stderr);
        $toolNames = array_map(static fn ($call) => strtolower($call->name), $assistant->toolCalls);
        $toolHaystack = implode("\n", $toolNames);

        $matched = [];
        foreach (self::VERIFICATION_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword) || str_contains($toolHaystack, $keyword)) {
                $matched[] = $keyword;
            }
        }

        if ([] === $matched) {
            return new EvaluationResult(
                name: self::NAME,
                score: 1.0,
                passed: false,
                explanation: 'No evidence that the assistant verified its work.',
                evidence: ['matched_keywords' => []],
            );
        }

        return new EvaluationResult(
            name: self::NAME,
            score: \count($matched) >= 2 ? 5.0 : 4.0,
            passed: true,
            explanation: \sprintf('Verification keywords detected: %s.', implode(', ', $matched)),
            evidence: ['matched_keywords' => $matched],
        );
    }
}
