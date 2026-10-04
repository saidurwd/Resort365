<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Models\Folio;

/**
 * A charge posted from the Folios tab: an extra from the catalogue (its charge code and price, the
 * price may be changed), or a charge code with an amount.
 */
class PostChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.folio.post') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $folio = $this->folio();

        return [
            'extra_service_id' => ['nullable', 'integer', TenantRule::exists('extra_services')->where('property_id', $folio->property_id)->where('is_active', true)->withoutTrashed()],
            'charge_code_id' => ['required_without:extra_service_id', 'nullable', 'integer', TenantRule::exists('charge_codes')->where('is_active', true)->withoutTrashed()],
            'unit_price' => ['required_without:extra_service_id', 'nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string', 'max:190'],
            'price_includes_tax' => ['boolean'],
        ];
    }

    public function charge(): FolioCharge
    {
        $folio = $this->folio();
        $extra = $this->filled('extra_service_id') ? ExtraService::query()->findOrFail((int) $this->validated('extra_service_id')) : null;
        $user = $this->user();

        return new FolioCharge(
            reservationId: (int) $folio->reservation_id,
            chargeCodeId: $extra !== null ? $extra->charge_code_id : (int) $this->validated('charge_code_id'),
            unitPrice: $this->filled('unit_price') ? (string) $this->validated('unit_price') : (string) $extra?->unit_price,
            quantity: (int) $this->validated('quantity'),
            description: $this->filled('description') ? (string) $this->validated('description') : $extra?->name,
            priceIncludesTax: $extra !== null && ! $this->filled('unit_price') ? $extra->price_includes_tax : $this->boolean('price_includes_tax'),
            folioId: $folio->id,
            extraServiceId: $extra?->id,
            postedBy: $user !== null ? (int) $user->getAuthIdentifier() : null,
        );
    }

    private function folio(): Folio
    {
        $folio = $this->route('folio');

        return $folio instanceof Folio ? $folio : abort(404);
    }
}
