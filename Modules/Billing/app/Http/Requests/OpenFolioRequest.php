<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioType;

/**
 * Another folio for a booking: who pays it (a company or travel agent needs the account).
 */
class OpenFolioRequest extends FormRequest
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
            'type' => ['required', Rule::in([FolioType::Company->value, FolioType::Master->value])],
            'bill_to_type' => ['required', Rule::enum(BillTo::class)],
            'company_id' => ['nullable', 'required_if:bill_to_type,company', 'integer', TenantRule::exists('companies')->withoutTrashed()],
            'travel_agent_id' => ['nullable', 'required_if:bill_to_type,travel_agent', 'integer', TenantRule::exists('travel_agents')->withoutTrashed()],
        ];
    }

    public function billToId(): ?int
    {
        return match (BillTo::from((string) $this->validated('bill_to_type'))) {
            BillTo::Company => (int) $this->validated('company_id'),
            BillTo::TravelAgent => (int) $this->validated('travel_agent_id'),
            BillTo::Guest => null,
        };
    }
}
