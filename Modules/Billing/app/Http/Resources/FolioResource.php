<?php

namespace Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;

/**
 * A folio as the API shows it (Step 8.1): its balance and, when loaded, its lines (voided lines included and flagged). Decimal
 * strings; a payment or refund line is negative on the folio the same way it is on screen.
 *
 * @mixin Folio
 */
class FolioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio_no' => $this->folio_no,
            'property_id' => $this->property_id,
            'reservation_id' => $this->reservation_id,
            'type' => $this->type->value,
            'name' => $this->name,
            'bill_to' => ['type' => $this->bill_to_type->value, 'id' => $this->bill_to_id],
            'status' => $this->status->value,
            'currency' => $this->currency_code,
            'balance' => $this->balance,
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (FolioLine $line): array => [
                'id' => $line->id,
                'posting_date' => $line->posting_date->toDateString(),
                'type' => $line->line_type->value,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
                'tax' => $line->tax_amount,
                'total' => $line->total,
                'is_voided' => $line->is_voided,
                'void_reason' => $line->void_reason,
            ])->all()),
        ];
    }
}
