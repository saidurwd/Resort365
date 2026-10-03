<?php

namespace Modules\Reservation\Services;

/**
 * Spreads a party over the chosen rooms and cottages (the wizard's starting point; staff can
 * change it). Every item gets an adult while adults last, then adults and children fill each
 * item up to its capacity, in order. Guests who do not fit stay on the last item.
 */
class PartySplitter
{
    /**
     * @param  array<string, array{adults: int, children: int, total: int}>  $capacities  item key => limits
     * @return array<string, array{adults: int, children: int}>
     */
    public function split(array $capacities, int $adults, int $children): array
    {
        $split = array_map(fn (): array => ['adults' => 0, 'children' => 0], $capacities);

        if ($split === []) {
            return [];
        }

        foreach (array_keys($split) as $key) {
            if ($adults > 0 && $capacities[$key]['adults'] > 0) {
                $split[$key]['adults']++;
                $adults--;
            }
        }

        foreach (array_keys($split) as $key) {
            $room = min($capacities[$key]['adults'] - $split[$key]['adults'], $capacities[$key]['total'] - $split[$key]['adults'], $adults);
            $split[$key]['adults'] += max(0, $room);
            $adults -= max(0, $room);
        }

        foreach (array_keys($split) as $key) {
            $used = $split[$key]['adults'];
            $room = min($capacities[$key]['children'], $capacities[$key]['total'] - $used, $children);
            $split[$key]['children'] += max(0, $room);
            $children -= max(0, $room);
        }

        $last = array_key_last($split);
        $split[$last]['adults'] += $adults;
        $split[$last]['children'] += $children;

        return $split;
    }
}
