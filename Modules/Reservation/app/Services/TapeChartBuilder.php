<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;

/**
 * Turns inventory locks into tape-chart bars (ARCHITECTURE §6.8) without database access: the
 * consecutive nights of one booking item (or one block) on one room become one bar, placed by
 * column (0 = the window's first day) and clipped to the window, with flags for bars that continue
 * before or after it.
 */
class TapeChartBuilder
{
    /**
     * @param  iterable<array{room_id: int, date: string, type: string, reservation_id: int|null, item_id: int|null, block: string|null}>  $locks
     * @return list<array{room_id: int, start: int, span: int, type: string, reservation_id: int|null, item_id: int|null, block: string|null, continues_before: bool, continues_after: bool}>
     */
    public function bars(iterable $locks, CarbonImmutable $from, int $days): array
    {
        $last = $days - 1;
        $grouped = [];

        foreach ($locks as $lock) {
            $column = (int) $from->diffInDays(CarbonImmutable::parse($lock['date']), false);
            $key = $lock['room_id'].'|'.($lock['item_id'] !== null ? 'i'.$lock['item_id'] : $lock['type'].':'.($lock['block'] ?? ''));
            $grouped[$key][] = ['column' => $column] + $lock;
        }

        $bars = [];

        foreach ($grouped as $nights) {
            usort($nights, fn (array $a, array $b): int => $a['column'] <=> $b['column']);
            $first = array_shift($nights);
            $run = ['first' => $first, 'start' => $first['column'], 'end' => $first['column']];

            foreach ($nights as $night) {
                if ($night['column'] === $run['end'] + 1) {
                    $run['end'] = $night['column'];

                    continue;
                }

                $bars[] = $this->clip($run, $last);
                $run = ['first' => $night, 'start' => $night['column'], 'end' => $night['column']];
            }

            $bars[] = $this->clip($run, $last);
        }

        $bars = array_values(array_filter($bars));
        usort($bars, fn (array $a, array $b): int => [$a['room_id'], $a['start']] <=> [$b['room_id'], $b['start']]);

        return $bars;
    }

    /**
     * @param  array{first: array<string, mixed>, start: int, end: int}  $run
     * @return array{room_id: int, start: int, span: int, type: string, reservation_id: int|null, item_id: int|null, block: string|null, continues_before: bool, continues_after: bool}|null
     */
    private function clip(array $run, int $last): ?array
    {
        if ($run['end'] < 0 || $run['start'] > $last) {
            return null;
        }

        $start = max(0, $run['start']);
        $end = min($last, $run['end']);

        return [
            'room_id' => (int) $run['first']['room_id'],
            'start' => $start,
            'span' => $end - $start + 1,
            'type' => (string) $run['first']['type'],
            'reservation_id' => $run['first']['reservation_id'] !== null ? (int) $run['first']['reservation_id'] : null,
            'item_id' => $run['first']['item_id'] !== null ? (int) $run['first']['item_id'] : null,
            'block' => $run['first']['block'] !== null ? (string) $run['first']['block'] : null,
            'continues_before' => $run['start'] < 0,
            'continues_after' => $run['end'] > $last,
        ];
    }
}
