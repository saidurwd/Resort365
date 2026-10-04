<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An adjustment: positive adds to the bill, negative is a credit; the reason is required.
 */
class PostAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.folio.adjust') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'not_in:0', 'min:-9999999999', 'max:9999999999'],
            'reason' => ['required', 'string', 'max:190'],
            'charge_code_id' => ['nullable', 'integer', TenantRule::exists('charge_codes')->withoutTrashed()],
        ];
    }
}
