<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\Settings;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\PricedNight;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Services\BookingQuoter;

/**
 * Saves a priced proposal (ARCHITECTURE §5.6) instead of booking it: the same BookingQuoter price
 * the wizard shows, frozen night by night, with a quote number and a validity date (setting
 * reservation.quote_valid_days). No rooms are held; ConvertQuote books it later at these prices.
 */
class SaveQuote extends Action
{
    public function __construct(
        private readonly BookingQuoter $quoter,
        private readonly DocumentNumbers $numbers,
        private readonly Settings $settings,
    ) {}

    /**
     * @throws BookingNotPossible|DepositBelowMinimum
     */
    public function handle(NewReservation $data): Quote
    {
        $quote = $this->quoter->quote($data);

        if (! $quote->depositWithinLimits && ! $data->allowDepositOverride) {
            throw new DepositBelowMinimum(__('A deposit of :percent% is outside the deposit policy. A manager can allow it.', ['percent' => (string) $data->depositPercent]));
        }

        $validDays = (int) $this->settings->get('reservation.quote_valid_days', $data->propertyId);

        return $this->transaction(function () use ($data, $quote, $validDays): Quote {
            $saved = Quote::query()->create([
                'property_id' => $data->propertyId,
                'code' => $this->numbers->next('quote', $data->propertyId),
                'status' => QuoteStatus::Draft,
                'source' => $data->source,
                'guest_id' => $data->primaryGuestId,
                'company_id' => $data->companyId,
                'travel_agent_id' => $data->travelAgentId,
                'rate_plan_id' => $data->items[0]->ratePlanId,
                'check_in' => $data->checkIn->toDateString(),
                'check_out' => $data->checkOut->toDateString(),
                'adults' => array_sum(array_map(fn (BookingItem $item): int => $item->adults, $data->items)),
                'children' => array_sum(array_map(fn (BookingItem $item): int => $item->children, $data->items)),
                'currency_code' => $quote->currency,
                'subtotal' => $quote->subtotal,
                'discount_total' => $quote->discount,
                'tax_total' => $quote->tax,
                'grand_total' => $quote->total,
                'deposit_percent' => $quote->deposit->percent,
                'deposit_amount' => $quote->deposit->amount,
                'promo_code' => $quote->promotion->code ?? ($data->promoCode !== null && $data->promoCode !== '' ? strtoupper($data->promoCode) : null),
                'promotion_id' => $quote->promotion?->promotionId,
                'valid_until' => now()->addDays(max(1, $validDays))->toDateString(),
                'special_requests' => $data->specialRequests,
                'internal_notes' => $data->internalNotes,
                'created_by' => $data->createdBy,
            ]);

            foreach ($quote->items as $line) {
                $item = $saved->items()->create([
                    'property_id' => $data->propertyId,
                    'item_type' => $line->item->type,
                    'cottage_id' => $line->cottageId,
                    'room_id' => $line->item->type === ItemType::Room ? $line->item->unitId : null,
                    'room_type_id' => $line->roomTypeId,
                    'cottage_type_id' => $line->cottageTypeId,
                    'rate_plan_id' => $line->item->ratePlanId,
                    'unit_key' => $line->quote->unitKey,
                    'label' => $line->label,
                    'adults' => $line->item->adults,
                    'children' => $line->item->children,
                    'subtotal' => $line->quote->subtotal,
                    'discount' => $line->quote->discount,
                    'tax' => $line->quote->tax,
                    'total' => $line->quote->total,
                    'meal_component' => $line->quote->mealComponent,
                    'promotion_id' => $line->quote->promotion?->promotionId,
                    'promotion_code' => $line->quote->promotion?->code,
                    'promotion_name' => $line->quote->promotion?->name,
                    'promotion_amount' => $line->quote->promotion?->amount,
                ]);

                $item->nights()->createMany(array_map(fn (PricedNight $night): array => [
                    'property_id' => $data->propertyId, 'stay_date' => $night->date, 'base_rate' => $night->base, 'extra_person_amount' => $night->extras,
                    'discount' => $night->discount, 'net_amount' => $night->net, 'tax_amount' => $night->tax, 'total_amount' => $night->total,
                    'rate_source' => $night->source, 'season_name' => $night->seasonName,
                ], $line->quote->nights));
            }

            return $saved->load('items');
        }, attempts: 3);
    }
}
