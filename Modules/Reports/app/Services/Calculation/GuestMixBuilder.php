<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reservation\DTOs\BookingFact;

/**
 * The guest reports (Step 7.1): nationality mix, repeat guests, and the top guests and companies. Only stays count (a booking
 * that was checked in or out); cancelled bookings and no-shows are left out. Pure: names and nationalities come in as maps.
 */
final class GuestMixBuilder
{
    /**
     * @param  list<BookingFact>  $bookings
     * @param  array<int, string|null>  $nationalities  guest id => country code
     * @param  array<string, string>  $countries  country code => name
     */
    public function nationalities(array $bookings, array $nationalities, array $countries, string $title): ReportTable
    {
        $groups = [];
        $total = 0;

        foreach ($this->stays($bookings) as $stay) {
            $code = $nationalities[$stay->guestId] ?? null;
            $key = $code !== null && $code !== '' ? $code : '';
            $groups[$key] ??= ['guests' => [], 'stays' => 0, 'nights' => 0];
            $groups[$key]['guests'][$stay->guestId] = true;
            $groups[$key]['stays']++;
            $groups[$key]['nights'] += $stay->roomNights();
            $total += $stay->roomNights();
        }

        uasort($groups, fn (array $a, array $b): int => $b['nights'] <=> $a['nights']);
        $rows = [];
        $guests = [];

        foreach ($groups as $code => $group) {
            $rows[] = [$code === '' ? __('Not recorded') : ($countries[$code] ?? $code), (string) count($group['guests']), (string) $group['stays'], (string) $group['nights'], $this->share($group['nights'], $total)];
            $guests += $group['guests'];
        }

        $stays = array_sum(array_column($groups, 'stays'));

        return new ReportTable(
            $title, [__('Nationality'), __('Guests'), __('Stays'), __('Room nights'), __('Share of room nights')], $rows, [__('Total'), (string) count($guests), (string) $stays, (string) $total, $total > 0 ? '100.0%' : '—'],
            [['label' => __('Guests'), 'value' => (string) count($guests)], ['label' => __('Nationalities'), 'value' => (string) count(array_filter(array_keys($groups), fn (string|int $code): bool => $code !== ''))], ['label' => __('Room nights'), 'value' => (string) $total]],
            __('Guests are the primary guest of each stay that arrived in the period, counted once. A stay is a booking that was checked in.'),
        );
    }

    /**
     * @param  list<BookingFact>  $bookings
     * @param  array<int, int>  $lifetimeStays  guest id => stays ever
     * @param  array<int, string>  $names
     */
    public function repeatGuests(array $bookings, array $lifetimeStays, array $names, int $limit, string $title): ReportTable
    {
        $guests = [];

        foreach ($this->stays($bookings) as $stay) {
            $guests[$stay->guestId] ??= ['stays' => 0, 'nights' => 0, 'last' => ''];
            $guests[$stay->guestId]['stays']++;
            $guests[$stay->guestId]['nights'] += $stay->roomNights();
            $guests[$stay->guestId]['last'] = max($guests[$stay->guestId]['last'], $stay->checkIn);
        }

        $repeat = array_filter($guests, fn (int $id): bool => ($lifetimeStays[$id] ?? 0) >= 2, ARRAY_FILTER_USE_KEY);
        uksort($repeat, fn (int $a, int $b): int => ($lifetimeStays[$b] ?? 0) <=> ($lifetimeStays[$a] ?? 0) ?: $a <=> $b);
        $rows = [];

        foreach (array_slice($repeat, 0, max(1, $limit), true) as $id => $guest) {
            $rows[] = [$names[$id] ?? '#'.$id, (string) $guest['stays'], (string) ($lifetimeStays[$id] ?? 0), (string) $guest['nights'], $guest['last']];
        }

        return new ReportTable(
            $title, [__('Guest'), __('Stays in the period'), __('Stays in all'), __('Room nights in the period'), __('Last arrival')], $rows, null,
            [['label' => __('Guests in the period'), 'value' => (string) count($guests)], ['label' => __('Repeat guests'), 'value' => (string) count($repeat)], ['label' => __('Repeat share'), 'value' => $this->share(count($repeat), count($guests))]],
            __('A repeat guest has stayed two or more times in all. The most frequent are listed first.'),
        );
    }

    /**
     * @param  list<BookingFact>  $bookings
     * @param  array<int, string>  $names  guest id => name
     */
    public function topGuests(array $bookings, array $names, int $limit, string $title): ReportTable
    {
        $guests = [];

        foreach ($this->stays($bookings) as $stay) {
            $guests[$stay->guestId] ??= ['stays' => 0, 'nights' => 0, 'revenue' => BigDecimal::zero()];
            $guests[$stay->guestId]['stays']++;
            $guests[$stay->guestId]['nights'] += $stay->roomNights();
            $guests[$stay->guestId]['revenue'] = $guests[$stay->guestId]['revenue']->plus($stay->total);
        }

        return $this->ranked($guests, $names, $limit, $title, __('Guest'), __('The guests who spent most on their stays in the period (booking totals with tax).'));
    }

    /**
     * @param  list<BookingFact>  $bookings
     * @param  array<int, string>  $names  company id => name
     */
    public function topCompanies(array $bookings, array $names, int $limit, string $title): ReportTable
    {
        $companies = [];

        foreach ($this->stays($bookings) as $stay) {
            if ($stay->companyId === null) {
                continue;
            }

            $companies[$stay->companyId] ??= ['stays' => 0, 'nights' => 0, 'revenue' => BigDecimal::zero()];
            $companies[$stay->companyId]['stays']++;
            $companies[$stay->companyId]['nights'] += $stay->roomNights();
            $companies[$stay->companyId]['revenue'] = $companies[$stay->companyId]['revenue']->plus($stay->total);
        }

        return $this->ranked($companies, $names, $limit, $title, __('Company'), __('The companies whose bookings brought the most in the period (booking totals with tax).'));
    }

    /**
     * @param  array<int, array{stays: int, nights: int, revenue: BigDecimal}>  $groups
     * @param  array<int, string>  $names
     */
    private function ranked(array $groups, array $names, int $limit, string $title, string $first, string $note): ReportTable
    {
        uasort($groups, fn (array $a, array $b): int => $b['revenue']->compareTo($a['revenue']) ?: $b['nights'] <=> $a['nights']);
        $rows = [];

        foreach (array_slice($groups, 0, max(1, $limit), true) as $id => $group) {
            $rows[] = [$names[$id] ?? '#'.$id, (string) $group['stays'], (string) $group['nights'], number_format((float) (string) $group['revenue'], 2)];
        }

        $revenue = array_reduce($groups, fn (BigDecimal $sum, array $group): BigDecimal => $sum->plus($group['revenue']), BigDecimal::zero());

        return new ReportTable(
            $title, [$first, __('Stays'), __('Room nights'), __('Revenue')], $rows, null,
            [['label' => $first === __('Guest') ? __('Guests') : __('Companies'), 'value' => (string) count($groups)], ['label' => __('Revenue'), 'value' => number_format((float) (string) $revenue, 2)]], $note,
        );
    }

    /**
     * @param  list<BookingFact>  $bookings
     * @return list<BookingFact>
     */
    private function stays(array $bookings): array
    {
        return array_values(array_filter($bookings, fn (BookingFact $booking): bool => in_array($booking->status, ['checked_in', 'checked_out'], true)));
    }

    private function share(int $part, int $whole): string
    {
        return $whole > 0 ? BigDecimal::of($part)->multipliedBy(100)->dividedBy($whole, 1, RoundingMode::HalfUp).'%' : '—';
    }
}
