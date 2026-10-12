<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.rfq.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', TenantRule::exists('vendors')],
            'reference' => ['nullable', 'string', 'max:60'],
            'quoted_on' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:300'],
            'prices' => ['required', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lead_days' => ['nullable', 'array'],
            'lead_days.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return list<array{item_id: int, unit_price: string, lead_days: int|null}>
     */
    public function prices(): array
    {
        $rows = [];

        foreach ((array) $this->validated('prices') as $itemId => $price) {
            $days = ((array) $this->validated('lead_days', []))[$itemId] ?? null;
            $rows[] = ['item_id' => (int) $itemId, 'unit_price' => (string) ($price ?? ''), 'lead_days' => $days !== null && $days !== '' ? (int) $days : null];
        }

        return $rows;
    }
}
