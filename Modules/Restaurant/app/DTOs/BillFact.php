<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;

/**
 * A settled restaurant bill as the ledger needs it (Accounting, Step 4.3). Revenue is net of discounts,
 * by food and beverage; `payments` is what each tender received (tips included) and `tips` the tip part.
 * Amounts are decimal strings.
 */
final readonly class BillFact extends Data
{
    /**
     * @param  array{food: string, beverage: string}  $revenue
     * @param  array<string, string>  $taxes  tax name => amount (service charge excluded)
     * @param  array<string, string>  $payments  restaurant payment method => amount received
     */
    public function __construct(
        public int $billId,
        public string $billNo,
        public int $propertyId,
        public int $outletId,
        public string $outletName,
        public string $businessDate,
        public array $revenue,
        public string $serviceCharge,
        public array $taxes,
        public string $grandTotal,
        public string $tips,
        public array $payments,
        public bool $complimentary,
        public bool $voided,
    ) {}
}
