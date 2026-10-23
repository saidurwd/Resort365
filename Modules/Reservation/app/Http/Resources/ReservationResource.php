<?php

namespace Modules\Reservation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Models\ReservationItem;

/**
 * A booking as the API shows it (Step 8.1). Money is a decimal string in the booking's currency; dates are `Y-m-d`, times ISO 8601.
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
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
            'payment_status' => $this->payment_status->value,
            'source' => $this->source->value,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => (int) $this->check_in->diffInDays($this->check_out),
            'adults' => $this->adults,
            'children' => $this->children,
            'primary_guest_id' => $this->primary_guest_id,
            'company_id' => $this->company_id,
            'travel_agent_id' => $this->travel_agent_id,
            'rate_plan_id' => $this->rate_plan_id,
            'group_name' => $this->group_name,
            'currency' => $this->currency_code,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'deposit' => [
                'percent' => $this->deposit_percent,
                'required' => $this->deposit_required,
                'due_at' => $this->deposit_due_at?->toIso8601String(),
            ],
            'amount_paid' => $this->amount_paid,
            'balance_due' => $this->balance_due,
            'balance_due_on' => $this->balance_due_on?->toDateString(),
            'promo_code' => $this->promo_code,
            'special_requests' => $this->special_requests,
            'cancellation' => $this->cancelled_at === null ? null : [
                'cancelled_at' => $this->cancelled_at->toIso8601String(),
                'reason' => $this->cancellation_reason,
                'fee' => $this->cancellation_fee,
            ],
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn (ReservationItem $item): array => [
                'id' => $item->id,
                'type' => $item->item_type->value,
                'room_id' => $item->room_id,
                'room_type_id' => $item->room_type_id,
                'cottage_id' => $item->cottage_id,
                'cottage_type_id' => $item->cottage_type_id,
                'rate_plan_id' => $item->rate_plan_id,
                'adults' => $item->adults,
                'children' => $item->children,
                'status' => $item->status->value,
                'subtotal' => $item->subtotal,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'total' => $item->total,
            ])->all()),
            'guest_ids' => $this->whenLoaded('guests', fn (): array => $this->guests->map(fn (ReservationGuest $guest): int => $guest->guest_id)->all()),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
