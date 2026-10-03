<?php

namespace Modules\Guest\Services;

use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\DTOs\TravelAgentSummary;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Models\TravelAgent;

class GuestLookupService implements GuestLookup
{
    public function __construct(
        private readonly GuestSearch $guestSearch,
        private readonly DuplicateGuestFinder $duplicates,
    ) {}

    public function findDuplicates(?string $phone, ?string $email): array
    {
        return $this->duplicates->find($phone, $email, null, null)->map(fn (Guest $guest): GuestSummary => $this->guest($guest))->values()->all();
    }

    public function search(string $term, int $limit = 10): array
    {
        return $this->guestSearch->apply(Guest::query(), $term)->orderBy('first_name')->orderBy('last_name')->limit($limit)->get()
            ->map(fn (Guest $guest): GuestSummary => $this->guest($guest))->values()->all();
    }

    public function find(int $guestId): ?GuestSummary
    {
        $guest = Guest::withTrashed()->find($guestId);

        // Follow merges to the profile that was kept (at most a few hops).
        for ($hops = 0; $guest instanceof Guest && $guest->merged_into_id !== null && $hops < 5; $hops++) {
            $guest = Guest::withTrashed()->find($guest->merged_into_id);
        }

        return $guest instanceof Guest && ! $guest->trashed() ? $this->guest($guest) : null;
    }

    public function isBlacklisted(int $guestId): bool
    {
        $guest = $this->find($guestId);

        return $guest instanceof GuestSummary && $guest->isBlacklisted;
    }

    public function searchCompanies(string $term, int $limit = 10): array
    {
        return Company::query()->where('is_active', true)->where('name', 'like', $this->prefix($term))->orderBy('name')->limit($limit)->get()
            ->map(fn (Company $company): CompanySummary => $this->company($company))->values()->all();
    }

    public function findCompany(int $companyId): ?CompanySummary
    {
        $company = Company::query()->find($companyId);

        return $company instanceof Company ? $this->company($company) : null;
    }

    public function searchTravelAgents(string $term, int $limit = 10): array
    {
        $prefix = $this->prefix($term);

        return TravelAgent::query()->where('is_active', true)
            ->where(fn ($query) => $query->where('name', 'like', $prefix)->orWhere('code', 'like', $prefix))
            ->orderBy('name')->limit($limit)->get()
            ->map(fn (TravelAgent $agent): TravelAgentSummary => $this->travelAgent($agent))->values()->all();
    }

    public function findTravelAgent(int $travelAgentId): ?TravelAgentSummary
    {
        $agent = TravelAgent::query()->find($travelAgentId);

        return $agent instanceof TravelAgent ? $this->travelAgent($agent) : null;
    }

    private function guest(Guest $guest): GuestSummary
    {
        return new GuestSummary(
            id: $guest->id,
            name: $guest->full_name,
            email: $guest->email,
            phone: $guest->phone,
            nationalityCode: $guest->nationality_code,
            companyId: $guest->company_id,
            vipLevel: $guest->vip_level->value,
            isBlacklisted: $guest->is_blacklisted,
            blacklistReason: $guest->blacklist_reason,
        );
    }

    private function company(Company $company): CompanySummary
    {
        return new CompanySummary($company->id, $company->name, $company->tax_number, $company->credit_limit, $company->payment_terms_days, $company->is_active);
    }

    private function travelAgent(TravelAgent $agent): TravelAgentSummary
    {
        return new TravelAgentSummary($agent->id, $agent->code, $agent->name, $agent->commission_percent, $agent->credit_limit, $agent->is_active);
    }

    private function prefix(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($term)).'%';
    }
}
