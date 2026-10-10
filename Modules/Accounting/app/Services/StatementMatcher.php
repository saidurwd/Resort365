<?php

namespace Modules\Accounting\Services;

use Carbon\CarbonImmutable;

/**
 * Suggests which statement lines belong to which ledger lines (Step 4.4): the same signed amount and a date
 * within the window. A statement reference found in the ledger line's text ranks first, then the nearer date;
 * each line is used once. Pure; amounts are decimal strings, deposits and debits positive.
 */
class StatementMatcher
{
    /**
     * @param  list<array{id: int, date: string, amount: string, reference: string|null}>  $statement
     * @param  list<array{id: int, date: string, amount: string, text: string}>  $ledger
     * @return list<array{statement: int, ledger: int}>
     */
    public function suggest(array $statement, array $ledger, int $windowDays): array
    {
        $pairs = [];

        foreach ($statement as $line) {
            foreach ($ledger as $candidate) {
                if (bccomp($line['amount'], $candidate['amount'], 2) !== 0) {
                    continue;
                }

                $days = (int) abs(CarbonImmutable::parse($line['date'])->diffInDays(CarbonImmutable::parse($candidate['date'])));

                if ($days > $windowDays) {
                    continue;
                }

                $reference = trim((string) $line['reference']);
                $hit = $reference !== '' && stripos($candidate['text'], $reference) !== false;
                $pairs[] = ['statement' => $line['id'], 'ledger' => $candidate['id'], 'score' => ($hit ? 0 : 1000) + $days];
            }
        }

        usort($pairs, fn (array $a, array $b): int => [$a['score'], $a['statement'], $a['ledger']] <=> [$b['score'], $b['statement'], $b['ledger']]);
        $usedStatement = [];
        $usedLedger = [];
        $matches = [];

        foreach ($pairs as $pair) {
            if (isset($usedStatement[$pair['statement']]) || isset($usedLedger[$pair['ledger']])) {
                continue;
            }

            $usedStatement[$pair['statement']] = true;
            $usedLedger[$pair['ledger']] = true;
            $matches[] = ['statement' => $pair['statement'], 'ledger' => $pair['ledger']];
        }

        return $matches;
    }
}
