<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Models\CashierShift;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Finds a cashier's open shift (ARCHITECTURE §5.9): every payment and refund a user takes while
 * their shift at that property is open belongs to it, so the shift's cash can be counted; every
 * payment also records the business date it was taken on.
 */
class ShiftRegister
{
    public function __construct(
        private readonly PropertyDirectory $properties,
    ) {}

    public function openShift(?int $userId, int $propertyId): ?CashierShift
    {
        if ($userId === null) {
            return null;
        }

        return CashierShift::query()->where('property_id', $propertyId)->where('user_id', $userId)
            ->where('status', CashierShiftStatus::Open->value)->first();
    }

    /**
     * The columns every new payment gets: the business date it is taken on and the receiving
     * user's open shift.
     *
     * @return array{business_date: string, cashier_shift_id: int|null}
     */
    public function stamp(?int $userId, int $propertyId): array
    {
        return ['business_date' => $this->properties->find($propertyId)->businessDate ?? now()->toDateString(), 'cashier_shift_id' => $this->openShift($userId, $propertyId)?->id];
    }
}
