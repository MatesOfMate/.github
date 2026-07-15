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
 * Rule-based root-cause matcher.
 *
 * `expected.root_cause` is a list of keyword groups. Each group is either a
 * single phrase or a list of synonymous phrases; a group counts as matched
 * when any of its phrases occurs in the assistant's explanation or diff.
 * The score is proportional to the fraction of matched groups.
 *
 * To avoid unearned credit the haystack is cleaned before matching: the
 * workspace path (which embeds the scenario id) and any verbatim echo of the
 * task prompt are stripped, and phrases only match on word boundaries.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RootCauseEvaluator implements EvaluatorInterface
{
    public const NAME = 'root_cause';

    public function name(): string
    {
        return self::NAME;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $groups = $this->parseGroups($input->scenario->expected['root_cause'] ?? []);

        if ([] === $groups) {
            return EvaluationResult::notApplicable(
                self::NAME,
                'Scenario does not declare root_cause keywords; category excluded from scoring.',
                ['expected' => []],
            );
        }

        $haystack = $this->buildHaystack($input);
        $matched = [];
        $missing = [];

        foreach ($groups as $group) {
            $hit = null;
            foreach ($group as $phrase) {
                if ($this->containsPhrase($haystack, $phrase)) {
                    $hit = $phrase;
                    break;
                }
            }

            if (null !== $hit) {
                $matched[] = $hit;
            } else {
                $missing[] = implode(' | ', $group);
            }
        }

        $ratio = \count($matched) / \count($groups);
        $score = round($ratio * EvaluationResult::MAX_SCORE, 2);
        $passed = \count($matched) === \count($groups);

        return new EvaluationResult(
            name: self::NAME,
            score: $score,
            passed: $passed,
            explanation: \sprintf('%d/%d root-cause keyword groups matched.', \count($matched), \count($groups)),
            evidence: [
                'expected' => array_map(static fn (array $group): string => implode(' | ', $group), $groups),
                'matched' => $matched,
                'missing' => $missing,
            ],
        );
    }

    /**
     * @return list<non-empty-list<string>>
     */
    private function parseGroups(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $groups = [];

        foreach ($raw as $entry) {
            if (\is_string($entry) && '' !== trim($entry)) {
                $groups[] = [trim($entry)];
                continue;
            }

            if (\is_array($entry)) {
                $phrases = array_values(array_filter(
                    array_map(static fn ($phrase): string => \is_string($phrase) ? trim($phrase) : '', $entry),
                    static fn (string $phrase): bool => '' !== $phrase,
                ));

                if ([] !== $phrases) {
                    $groups[] = $phrases;
                }
            }
        }

        return $groups;
    }

    private function buildHaystack(EvaluationInput $input): string
    {
        $assistant = $input->outcome->assistantResult;
        $diff = $input->outcome->diff;

        $haystack = strtolower(implode("\n", array_filter([
            $assistant->stdout ?? '',
            $assistant->stderr ?? '',
            $diff->diff ?? '',
        ])));

        // Strip text that would trivially satisfy keywords without any
        // diagnosis: the workspace path embeds the scenario id, and a quoted
        // task prompt restates the problem statement.
        $noise = [
            strtolower($input->outcome->workspace->path),
            strtolower($input->scenario->id),
            strtolower(trim((string) ($input->scenario->task['prompt'] ?? ''))),
        ];

        foreach ($noise as $chunk) {
            if ('' !== $chunk) {
                $haystack = str_replace($chunk, ' ', $haystack);
            }
        }

        // Markdown emphasis must not defeat phrase matching: "used `-`
        // instead of `+`" should satisfy the phrase "- instead of +".
        return str_replace(['`', '*', '"', "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"], '', $haystack);
    }

    private function containsPhrase(string $haystack, string $phrase): bool
    {
        $phrase = trim(strtolower($phrase));
        if ('' === $phrase) {
            return false;
        }

        $pattern = '/(?<![a-z0-9_])'.preg_quote($phrase, '/').'(?![a-z0-9_])/';

        return 1 === preg_match($pattern, $haystack);
    }
}
