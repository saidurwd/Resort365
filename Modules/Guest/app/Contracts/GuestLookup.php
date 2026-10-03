<?php

namespace Modules\Guest\Contracts;

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
     * Guests by phone, email, ID number or name.
     *
     * @return list<GuestSummary>
     */
    public function search(string $term, int $limit = 10): array;

    public function find(int $guestId): ?GuestSummary;

    public function isBlacklisted(int $guestId): bool;

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
