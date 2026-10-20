<?php

namespace Modules\Reports\Services;

use App\Support\Tenancy\TenantCache;
use Carbon\CarbonImmutable;
use Modules\Accounting\Contracts\CashPosition;
use Modules\FrontOffice\Contracts\StatisticsLookup;
use Modules\FrontOffice\DTOs\DailyStatFact;
use Modules\Reports\Services\Calculation\KpiCalculator;
use Modules\Reports\Services\Calculation\Kpis;
use Modules\Reservation\Contracts\ReservationLookup;

/**
 * Everything the management dashboard shows (Step 7.1) for one or more properties: the night audit figures of the latest audited
 * day, the month and the year so far, a 30-day trend, a year of monthly revenue, the front desk's day and the cash position. The
 * statistics come from the night audit's daily table in three grouped queries; the result is kept for a minute.
 */
class DashboardData
{
    public function __construct(
        private readonly StatisticsLookup $statistics,
        private readonly KpiCalculator $kpis,
        private readonly ReservationLookup $reservations,
        private readonly CashPosition $cash,
        private readonly TenantCache $cache,
    ) {}

    /**
     * @param  list<int>  $propertyIds
     * @return array{date: string|null, day: Kpis, month: Kpis, year: Kpis, trend: array{labels: list<string>, occupancy: list<float>, revenue: list<float>}, months: array{labels: list<string>, rooms: list<float>, fnb: list<float>}}
     */
    public function statistics(array $propertyIds): array
    {
        sort($propertyIds);

        return $this->cache->remember('dashboard:stats:'.implode('-', $propertyIds), 60, function () use ($propertyIds): array {
            $date = $this->statistics->latestDate($propertyIds);
            $at = CarbonImmutable::parse($date ?? now()->toDateString());
            $day = $this->kpis->calculate($this->statistics->totals($propertyIds, $at->toDateString(), $at->toDateString()));
            $month = $this->kpis->calculate($this->statistics->totals($propertyIds, $at->startOfMonth()->toDateString(), $at->toDateString()));
            $year = $this->kpis->calculate($this->statistics->totals($propertyIds, $at->startOfYear()->toDateString(), $at->toDateString()));

            return ['date' => $date, 'day' => $day, 'month' => $month, 'year' => $year, 'trend' => $this->trend($propertyIds, $at), 'months' => $this->months($propertyIds, $at)];
        });
    }

    /**
     * The front desk's day for the properties: arrivals and departures still to come, rooms in house, and bookings waiting for a
     * deposit.
     *
     * @param  list<int>  $propertyIds
     * @return array{arrivals: int, departures: int, in_house: int, deposits: int, deposits_due: string}
     */
    public function frontDesk(array $propertyIds, string $today): array
    {
        $arrivals = 0;
        $departures = 0;
        $inHouse = 0;
        $deposits = 0;
        $due = '0.00';

        foreach ($propertyIds as $id) {
            $arrivals += count($this->reservations->arrivals($id, $today));
            $departures += count($this->reservations->departures($id, $today));
            $inHouse += count($this->reservations->inHouse($id));

            foreach ($this->reservations->pendingDeposits($id) as $summary) {
                $deposits++;
                $due = bcadd($due, bcsub((string) $summary->depositRequired, (string) $summary->amountPaid, 2), 2);
            }
        }

        return ['arrivals' => $arrivals, 'departures' => $departures, 'in_house' => $inHouse, 'deposits' => $deposits, 'deposits_due' => $due];
    }

    /**
     * @return array{total: string, accounts: list<array{name: string, kind: string, balance: string}>}
     */
    public function cash(string $date): array
    {
        $accounts = [];
        $total = '0.00';

        foreach ($this->cash->balances($date) as $balance) {
            $accounts[] = ['name' => $balance->name, 'kind' => $balance->kind, 'balance' => $balance->balance];
            $total = bcadd($total, $balance->balance, 2);
        }

        return ['total' => $total, 'accounts' => $accounts];
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array{labels: list<string>, occupancy: list<float>, revenue: list<float>}
     */
    private function trend(array $propertyIds, CarbonImmutable $at): array
    {
        $byDate = [];

        foreach ($this->statistics->between($propertyIds, $at->subDays(29)->toDateString(), $at->toDateString()) as $fact) {
            $byDate[$fact->date]['occupied'] = ($byDate[$fact->date]['occupied'] ?? 0) + $fact->roomsOccupied;
            $byDate[$fact->date]['available'] = ($byDate[$fact->date]['available'] ?? 0) + $fact->roomsAvailable;
            $byDate[$fact->date]['revenue'] = bcadd($byDate[$fact->date]['revenue'] ?? '0', $fact->roomRevenue, 2);
        }

        $labels = [];
        $occupancy = [];
        $revenue = [];

        foreach ($byDate as $date => $day) {
            $labels[] = CarbonImmutable::parse($date)->format('d M');
            $occupancy[] = $day['available'] > 0 ? round($day['occupied'] * 100 / $day['available'], 1) : 0.0;
            $revenue[] = (float) $day['revenue'];
        }

        return ['labels' => $labels, 'occupancy' => $occupancy, 'revenue' => $revenue];
    }

    /**
     * The room and F&B revenue of the last 12 months.
     *
     * @param  list<int>  $propertyIds
     * @return array{labels: list<string>, rooms: list<float>, fnb: list<float>}
     */
    private function months(array $propertyIds, CarbonImmutable $at): array
    {
        $first = $at->startOfMonth()->subMonths(11);
        $sums = [];

        foreach ($this->statistics->between($propertyIds, $first->toDateString(), $at->toDateString()) as $fact) {
            /** @var DailyStatFact $fact */
            $key = substr($fact->date, 0, 7);
            $sums[$key]['rooms'] = bcadd($sums[$key]['rooms'] ?? '0', $fact->roomRevenue, 2);
            $sums[$key]['fnb'] = bcadd($sums[$key]['fnb'] ?? '0', $fact->fnbSales, 2);
        }

        $labels = [];
        $rooms = [];
        $fnb = [];

        for ($month = $first; $month->lte($at->startOfMonth()); $month = $month->addMonth()) {
            $labels[] = $month->format('M Y');
            $rooms[] = (float) ($sums[$month->format('Y-m')]['rooms'] ?? 0);
            $fnb[] = (float) ($sums[$month->format('Y-m')]['fnb'] ?? 0);
        }

        return ['labels' => $labels, 'rooms' => $rooms, 'fnb' => $fnb];
    }
}
