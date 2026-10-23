<?php

namespace Modules\Reservation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Reservation\DTOs\PricedNight;
use Modules\Reservation\DTOs\PriceQuote;

/**
 * The price of one unit for a stay (Step 8.1): totals and each night. Decimal strings.
 *
 * @mixin PriceQuote
 */
class PriceQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rate_plan_id' => $this->ratePlanId,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'net' => $this->net,
            'tax' => $this->tax,
            'total' => $this->total,
            'promotion' => $this->promotion instanceof PromotionDiscount ? ['code' => $this->promotion->code, 'name' => $this->promotion->name, 'amount' => $this->promotion->amount] : null,
            'nights' => array_map(fn (PricedNight $night): array => [
                'date' => $night->date, 'base' => $night->base, 'extras' => $night->extras, 'discount' => $night->discount,
                'net' => $night->net, 'tax' => $night->tax, 'total' => $night->total,
            ], $this->nights),
        ];
    }
}
