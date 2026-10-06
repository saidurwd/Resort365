<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\ChargeableStay;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\MealPlans;

/**
 * Redeems an in-house guest's meal plan on an order (ARCHITECTURE §5.10.9): for a meal period and a number
 * of covers, checked against what the booking's rate plans include today less what it already took
 * (MealPlans). The order's items on the outlet's package menu (the price list's meal-plan flag) go on the
 * bill at nothing; the rest is billed as usual. More covers than included needs restaurant.package.override
 * or a manager's PIN (`package.over`). The redemption is recorded for headcount and reports; it posts no
 * revenue. Redeeming again replaces the order's redemption; clear() takes it off.
 */
class RedeemMealPlan extends Action
{
    public const string APPROVAL = 'package.over';

    public function __construct(
        private readonly MealPlans $plans,
        private readonly FolioPostingContract $folios,
        private readonly ManagerApprovals $approvals,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, int $reservationId, MealPeriod $period, int $adults, int $children, int $userId, bool $mayOverride, ?int $approvalId = null): PackageRedemption
    {
        return $this->transaction(function () use ($order, $reservationId, $period, $adults, $children, $userId, $mayOverride, $approvalId): PackageRedemption {
            $locked = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->isOpen()) {
                throw new PosNotAllowed(__('The bill is printed: reopen it to redeem a meal plan.'));
            }

            $stay = collect($this->folios->chargeableStays($locked->property_id))->firstWhere('reservationId', $reservationId);

            if (! $stay instanceof ChargeableStay) {
                throw new PosNotAllowed(__('Choose a guest who is in house.'));
            }

            if ($adults < 0 || $children < 0 || $adults + $children < 1) {
                throw new PosNotAllowed(__('Enter the covers having the meal.'));
            }

            $eligible = OutletMenuItem::query()->where('outlet_id', $locked->outlet_id)->where('is_package_eligible', true)->get(['menu_item_id', 'variant_key'])
                ->map(fn (OutletMenuItem $row): string => $row->menu_item_id.':'.$row->variant_key)->all();
            $lines = PosOrderLine::query()->where('pos_order_id', $locked->id)->where('status', '!=', OrderLineStatus::Voided->value)->get()
                ->filter(fn (PosOrderLine $line): bool => in_array($line->menu_item_id.':'.($line->menu_item_variant_id ?? 0), $eligible, true));

            if ($lines->isEmpty()) {
                throw new PosNotAllowed(__('Nothing on this order is on the meal-plan menu.'));
            }

            $left = $this->plans->remaining($locked->property_id, $reservationId, $period, $locked->id);
            $approval = null;

            if ($adults + $children > $left['left']) {
                $why = $left['entitled'] === 0
                    ? __(':guest\'s plan does not include :meal today.', ['guest' => $stay->guestName, 'meal' => mb_strtolower($period->label())])
                    : __(':guest has :left of :entitled :meal covers left today.', ['guest' => $stay->guestName, 'left' => $left['left'], 'entitled' => $left['entitled'], 'meal' => mb_strtolower($period->label())]);

                $approval = $mayOverride ? null : ($approvalId !== null
                    ? $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_order', $locked->id)
                    : throw PosNotAllowed::needsApproval($why.' '.__('More needs a manager\'s approval.'), self::APPROVAL));
            }

            $this->clear($locked);
            $redemption = PackageRedemption::query()->create([
                'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'reservation_id' => $reservationId, 'reservation_code' => $stay->code,
                'guest_name' => mb_substr($stay->guestName, 0, 190), 'business_date' => $this->plans->businessDate($locked->property_id), 'meal_period' => $period,
                'covers_adults' => $adults, 'covers_children' => $children, 'entitled' => $left['entitled'], 'pos_order_id' => $locked->id,
                'manager_approval_id' => $approval?->id, 'created_by' => $userId,
            ]);
            PosOrderLine::query()->whereIn('id', $lines->pluck('id'))->update(['package_redemption_id' => $redemption->id]);

            return $redemption;
        });
    }

    /**
     * Takes an open order's meal-plan redemption off: its items are billed as usual again.
     *
     * @throws PosNotAllowed
     */
    public function clear(PosOrder $order): void
    {
        if (! $order->isOpen()) {
            throw new PosNotAllowed(__('The bill is printed: reopen it to change the meal plan.'));
        }

        $this->transaction(function () use ($order): void {
            PosOrderLine::query()->where('pos_order_id', $order->id)->update(['package_redemption_id' => null]);
            PackageRedemption::query()->where('pos_order_id', $order->id)->get()->each(fn (PackageRedemption $redemption) => $redemption->delete());
        });
    }
}
