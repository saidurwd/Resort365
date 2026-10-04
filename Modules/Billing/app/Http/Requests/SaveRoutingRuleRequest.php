<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\ChargeCategory;

class SaveRoutingRuleRequest extends FormRequest
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
        return [
            'reservation_id' => ['required', 'integer', TenantRule::exists('reservations')],
            'category' => ['required', Rule::enum(ChargeCategory::class)],
            'target_folio_id' => ['required', 'integer', TenantRule::exists('folios')->where('reservation_id', (int) $this->input('reservation_id'))],
        ];
    }
}
