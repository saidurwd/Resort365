<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\CityLedgerAccounts;
use Modules\Billing\DTOs\CityLedgerAccount;
use Modules\Billing\DTOs\CityLedgerCharge;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;

class CityLedgerAccountsService implements CityLedgerAccounts
{
    public function __construct(
        private readonly GuestLookup $guests,
        private readonly FolioLedger $ledger,
    ) {}

    public function accounts(string $term): array
    {
        return array_values(array_map($this->summary(...),
            array_filter($this->guests->searchCompanies($term, 20), fn (CompanySummary $company): bool => $company->isActive)));
    }

    public function account(int $companyId): ?CityLedgerAccount
    {
        $company = $this->guests->findCompany($companyId);

        return $company instanceof CompanySummary ? $this->summary($company) : null;
    }

    private function summary(CompanySummary $company): CityLedgerAccount
    {
        $owed = $this->owed($company->id);
        $left = BigDecimal::of($company->creditLimit)->isPositive() ? (string) BigDecimal::of($company->creditLimit)->minus($owed)->toScale(2) : null;

        return new CityLedgerAccount($company->id, $company->name, (string) $owed->toScale(2), $left);
    }

    public function charge(CityLedgerCharge $charge): int
    {
        $company = $this->guests->findCompany($charge->companyId);

        if (! $company instanceof CompanySummary || ! $company->isActive) {
            throw new ChargeRejected(__('Choose an active company.'));
        }

        $amount = BigDecimal::of($charge->amount);

        if (BigDecimal::of($company->creditLimit)->isPositive() && $this->owed($company->id)->plus($amount)->isGreaterThan($company->creditLimit)) {
            throw new ChargeRejected(__(':company would go over its credit limit of :limit.', ['company' => $company->name, 'limit' => $company->creditLimit]));
        }

        $date = CarbonImmutable::parse($this->ledger->businessDate($charge->propertyId));

        return CityLedgerEntry::query()->create([
            'property_id' => $charge->propertyId, 'company_id' => $company->id, 'reference_type' => $charge->referenceType, 'reference_id' => $charge->referenceId,
            'posted_on' => $date->toDateString(), 'due_on' => $date->addDays($company->paymentTermsDays)->toDateString(), 'description' => $charge->description,
            'amount' => (string) $amount->toScale(2), 'status' => CityLedgerStatus::Open,
        ])->id;
    }

    public function cancel(int $entryId, string $reason): void
    {
        $entry = CityLedgerEntry::query()->lockForUpdate()->find($entryId) ?? throw new ChargeRejected(__('That city-ledger entry no longer exists.'));

        if (BigDecimal::of($entry->paid)->isPositive() || BigDecimal::of($entry->credited)->isPositive()) {
            throw new ChargeRejected(__('Money was already received against this account entry: it cannot be taken back.'));
        }

        $entry->forceFill(['status' => CityLedgerStatus::Cancelled, 'description' => mb_substr($entry->description.' · '.__('cancelled: :reason', ['reason' => $reason]), 0, 250)])->save();
    }

    private function owed(int $companyId): BigDecimal
    {
        return CityLedgerEntry::query()->where('company_id', $companyId)->where('status', CityLedgerStatus::Open->value)->get()
            ->reduce(fn (BigDecimal $sum, CityLedgerEntry $entry): BigDecimal => $sum->plus($entry->open()), BigDecimal::zero());
    }
}
