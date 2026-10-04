<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\FolioLedger;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;

/**
 * Moves a folio's balance to a company's city-ledger account (ARCHITECTURE §5.9, §7.1 "settlement
 * to company account"): a transfer line settles the folio and an entry records what the company
 * owes, due after its payment terms. The company's credit limit (if any) covers all it owes.
 */
class TransferToCityLedger extends Action
{
    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly GuestLookup $guests,
    ) {}

    /**
     * @throws ChargeRejected
     */
    public function handle(Folio $folio, int $companyId, ?int $userId = null): CityLedgerEntry
    {
        $company = $this->guests->findCompany($companyId);

        if (! $company instanceof CompanySummary || ! $company->isActive) {
            throw new ChargeRejected(__('Choose an active company.'));
        }

        return $this->transaction(function () use ($folio, $company, $userId): CityLedgerEntry {
            $locked = Folio::query()->lockForUpdate()->findOrFail($folio->id);
            $amount = BigDecimal::of($locked->balance);

            if (! $locked->isOpen() || ! $amount->isPositive()) {
                throw new ChargeRejected(__('Folio :no has nothing to transfer.', ['no' => $locked->folio_no]));
            }

            $owed = CityLedgerEntry::query()->where('company_id', $company->id)->where('status', CityLedgerStatus::Open->value)->get()
                ->reduce(fn (BigDecimal $sum, CityLedgerEntry $entry): BigDecimal => $sum->plus($entry->open()), BigDecimal::zero());

            if (BigDecimal::of($company->creditLimit)->isPositive() && $owed->plus($amount)->isGreaterThan($company->creditLimit)) {
                throw new ChargeRejected(__(':company would go over its credit limit of :limit.', ['company' => $company->name, 'limit' => $company->creditLimit]));
            }

            $date = CarbonImmutable::parse($this->ledger->businessDate($locked->property_id));
            $entry = CityLedgerEntry::query()->create([
                'property_id' => $locked->property_id,
                'company_id' => $company->id,
                'folio_id' => $locked->id,
                'posted_on' => $date->toDateString(),
                'due_on' => $date->addDays($company->paymentTermsDays)->toDateString(),
                'description' => __('Folio :no (:name)', ['no' => $locked->folio_no, 'name' => $locked->name]),
                'amount' => (string) $amount->toScale(2),
                'status' => CityLedgerStatus::Open,
            ]);

            FolioLine::query()->create([
                'property_id' => $locked->property_id, 'folio_id' => $locked->id, 'posting_date' => $date->toDateString(),
                'line_type' => FolioLineType::Transfer, 'description' => __('To :company\'s account (city ledger)', ['company' => $company->name]),
                'quantity' => '1', 'unit_price' => $entry->amount, 'amount' => $entry->amount, 'tax_amount' => '0', 'total' => $entry->amount,
                'reference_type' => 'city_ledger_entry', 'reference_id' => $entry->id, 'posted_by' => $userId,
            ]);
            $this->ledger->recalculate($locked);

            return $entry;
        });
    }
}
