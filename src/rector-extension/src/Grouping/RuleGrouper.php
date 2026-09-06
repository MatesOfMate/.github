<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Grouping;

/**
 * Groups a refactoring run by the Rector rule that produced the changes.
 *
 * Rector already names the rule behind every change, so unlike test failures or
 * static analysis messages there is nothing to infer from text: the grouping key
 * is reported. What is missing is the shape. A sweep over a legacy namespace
 * returns one diff per file, and reading twelve diffs to discover that four
 * rules fired everywhere is the expensive way to learn four facts.
 *
 * A file is normally changed by several rules at once, so groups overlap. They
 * count files rather than owning them, and the diffs stay addressable per file.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RuleGrouper
{
    /**
     * @param array<string, array<int, string>> $ruleFiles rule class => files it changed
     *
     * @return array<int, array<string, mixed>> groups, widest reach first
     */
    public function group(array $ruleFiles): array
    {
        $groups = [];

        foreach ($ruleFiles as $rule => $files) {
            $files = array_values(array_unique($files));
            $groups[] = [
                'rule' => $rule,
                'short' => $this->shortName($rule),
                'set' => $this->setName($rule),
                'count' => \count($files),
                'files' => $files,
            ];
        }

        usort($groups, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp((string) $a['rule'], (string) $b['rule']));

        foreach ($groups as $i => &$group) {
            $group['id'] = 'g'.($i + 1);
        }

        return $groups;
    }

    /**
     * The class name without its namespace: `LongArrayToShortArrayRector`.
     */
    public function shortName(string $rule): string
    {
        $parts = explode('\\', $rule);

        return end($parts) ?: $rule;
    }

    /**
     * The set a rule belongs to, taken from the second segment of its namespace
     * (`Rector\DeadCode\Rector\...` gives `DeadCode`). It is what says whether a
     * sweep was a language upgrade or a code-quality pass.
     */
    public function setName(string $rule): string
    {
        $parts = explode('\\', $rule);

        return $parts[1] ?? '';
    }
}
