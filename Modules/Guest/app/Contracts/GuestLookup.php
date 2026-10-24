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

    /**
     * The nationality (ISO country code) of several guests at once, keyed by the ids asked for; a guest with none is null (Step 7.1).
     *
     * @param  list<int>  $guestIds
     * @return array<int, string|null>
     */
    public function nationalities(array $guestIds): array;

    public function isBlacklisted(int $guestId): bool;

    /**
     * Guests with the same phone or email (as the guest form's duplicate warning finds them).
     *
     * @return list<GuestSummary>
     */
    public function findDuplicates(?string $phone, ?string $email): array;

    /**
     * The one guest whose email and phone are both these (the phone normalised as stored), or null when none or several match
     * (Step 8.2b: a website booking reuses the profile only when it is certainly the same person).
     */
    public function matchContact(string $email, string $phone): ?GuestSummary;

    /**
     * Names of several companies at once, keyed by the ids asked for (Step 7.1).
     *
     * @param  list<int>  $companyIds
     * @return array<int, string>
     */
    public function companyNames(array $companyIds): array;

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
