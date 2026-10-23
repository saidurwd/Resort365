<?php

namespace Modules\Reservation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\Enums\ItemType;

/**
 * The price of a whole stay (Step 8.1): each unit, the totals, the deposit due and any promotion. Decimal strings.
 *
 * @mixin BookingQuote
 */
class BookingQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'deposit' => [
                'percent' => $this->deposit->percent,
                'amount' => $this->deposit->amount,
                'balance' => $this->deposit->balance,
                'due_at' => $this->deposit->dueAt,
                'balance_due_on' => $this->deposit->balanceDueOn,
                'full_payment_required' => $this->deposit->fullPaymentRequired,
            ],
            'promotion' => $this->promotion instanceof PromotionDiscount ? ['code' => $this->promotion->code, 'name' => $this->promotion->name, 'amount' => $this->promotion->amount] : null,
            'items' => array_map(fn (QuotedItem $item): array => [
                'type' => $item->item->type->value,
                'label' => $item->label,
                'room_id' => $item->item->type === ItemType::Room ? $item->item->unitId : null,
                'room_type_id' => $item->roomTypeId,
                'cottage_id' => $item->cottageId,
                'cottage_type_id' => $item->cottageTypeId,
                'adults' => $item->item->adults,
                'children' => $item->item->children,
                'price' => new PriceQuoteResource($item->quote)->toArray($request),
            ], $this->items),
        ];
    }
}
