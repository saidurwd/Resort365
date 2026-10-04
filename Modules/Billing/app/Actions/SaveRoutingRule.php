<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioRoutingRule;

/**
 * Routes a reservation's future charges of a category to one of its folios ("company pays room,
 * guest pays F&B"). Charges already posted stay where they are. Routing to the guest folio
 * removes the rule (that is the default).
 */
class SaveRoutingRule extends Action
{
    /**
     * @throws ChargeRejected
     */
    public function handle(int $reservationId, ChargeCategory $category, Folio $target): ?FolioRoutingRule
    {
        if ($target->reservation_id !== $reservationId) {
            throw new ChargeRejected(__('That folio does not belong to this booking.'));
        }

        $existing = FolioRoutingRule::query()->where('reservation_id', $reservationId)->where('category', $category->value)->first();

        if ($target->type === FolioType::Guest) {
            $existing?->delete();

            return null;
        }

        $rule = $existing ?? new FolioRoutingRule(['property_id' => $target->property_id, 'reservation_id' => $reservationId, 'category' => $category]);
        $rule->fill(['target_folio_id' => $target->id])->save();

        return $rule;
    }
}
