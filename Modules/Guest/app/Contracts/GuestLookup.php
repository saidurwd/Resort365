<?php

namespace Modules\Guest\Contracts;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Modules\Guest\DTOs\CompanySummary;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\DTOs\TravelAgentSummary;

/**
 * How other modules (Reservation, Front Office, Billing…) find guests, companies and travel
 * agents of the current tenant. Merged guests resolve to the profile that was kept.
 */
interface GuestLookup
{
    /**
     * Guests by phone, email, ID number or name. $among narrows the search to some guest ids: a
     * list, or a query selecting one column of ids (e.g. the guests of a property's bookings), so a
     * module can search "its" guests without a long id list.
     *
     * @param  list<int>|QueryBuilder|null  $among
     * @return list<GuestSummary>
     */
    public function search(string $term, int $limit = 10, array|QueryBuilder|null $among = null): array;

    public function find(int $guestId): ?GuestSummary;

    /**
     * Full names of several guests at once (for lists), keyed by the ids asked for. Merged guests
     * show the kept profile's name; unknown ids are missing.
     *
     * @param  list<int>  $guestIds
     * @return array<int, string>
     */
    public function names(array $guestIds): array;

    public function isBlacklisted(int $guestId): bool;

    /**
     * Guests with the same phone or email (as the guest form's duplicate warning finds them).
     *
     * @return list<GuestSummary>
     */
    public function findDuplicates(?string $phone, ?string $email): array;

    /**
     * Active companies whose name starts with the term.
     *
     * @return list<CompanySummary>
     */
    public function searchCompanies(string $term, int $limit = 10): array;

    public function findCompany(int $companyId): ?CompanySummary;

    /**
     * Active travel agents whose name or code starts with the term.
     *
     * @return list<TravelAgentSummary>
     */
    public function searchTravelAgents(string $term, int $limit = 10): array;

    public function findTravelAgent(int $travelAgentId): ?TravelAgentSummary;
}
