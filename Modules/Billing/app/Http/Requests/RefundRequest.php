<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\RefundKind;

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.refund.issue') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer', TenantRule::exists('reservations')],
            'kind' => ['required', Rule::enum(RefundKind::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'reason' => ['required', 'string', 'max:190'],
            'source_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'return_to' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function refund(): NewRefund
    {
        $user = $this->user();

        return new NewRefund((int) $this->validated('reservation_id'), RefundKind::from((string) $this->validated('kind')), PaymentMethod::from((string) $this->validated('method')),
            (string) $this->validated('amount'), (string) $this->validated('reason'), $this->filled('source_id') ? (int) $this->validated('source_id') : null,
            $this->filled('reference') ? (string) $this->validated('reference') : null, $user !== null ? (int) $user->getAuthIdentifier() : null);
    }

    public function returnTo(): ?string
    {
        $url = $this->validated('return_to');

        return is_string($url) && str_starts_with($url, $this->getSchemeAndHttpHost().'/') ? $url : null;
    }
}
