<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Services\FolioLedger;

/**
 * Opens a cashier's shift at a property with a cash float (ARCHITECTURE §5.9). A cashier has at
 * most one open shift per property (unique open_user_id).
 */
class OpenShift extends Action
{
    public function __construct(
        private readonly FolioLedger $ledger,
    ) {}

    /**
     * @throws PaymentNotAllowed
     */
    public function handle(int $propertyId, int $userId, string $openingFloat): CashierShift
    {
        $float = BigDecimal::of($openingFloat)->toScale(2);

        if ($float->isNegative()) {
            throw new PaymentNotAllowed(__('The float cannot be negative.'));
        }

        try {
            return CashierShift::query()->create([
                'property_id' => $propertyId,
                'user_id' => $userId,
                'business_date' => $this->ledger->businessDate($propertyId),
                'opened_at' => now(),
                'opening_float' => (string) $float,
                'status' => CashierShiftStatus::Open,
                'open_user_id' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new PaymentNotAllowed(__('You already have an open shift here.'));
        }
    }
}
