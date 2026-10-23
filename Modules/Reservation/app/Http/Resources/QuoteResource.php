<?php

namespace Modules\Reservation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\QuoteItem;

/**
 * A saved quote (Step 8.1): its number, status, validity and the prices frozen for the guest. Decimal strings.
 *
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'property_id' => $this->property_id,
            'status' => $this->status->value,
            'guest_id' => $this->guest_id,
            'rate_plan_id' => $this->rate_plan_id,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'adults' => $this->adults,
            'children' => $this->children,
            'currency' => $this->currency_code,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'deposit' => ['percent' => $this->deposit_percent, 'amount' => $this->deposit_amount],
            'promo_code' => $this->promo_code,
            'valid_until' => $this->valid_until->toDateString(),
            'reservation_id' => $this->reservation_id,
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn (QuoteItem $item): array => [
                'type' => $item->item_type->value,
                'label' => $item->label,
                'room_id' => $item->room_id,
                'room_type_id' => $item->room_type_id,
                'cottage_id' => $item->cottage_id,
                'cottage_type_id' => $item->cottage_type_id,
                'adults' => $item->adults,
                'children' => $item->children,
                'total' => $item->total,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
