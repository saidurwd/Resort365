<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Payment;

/**
 * A manual payment for a reservation (the Payments tab).
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer', TenantRule::exists('reservations')],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'security_deposit' => ['boolean'],
            'return_to' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function payment(): NewPayment
    {
        $user = $this->user();

        return new NewPayment(
            (int) $this->validated('reservation_id'),
            PaymentMethod::from((string) $this->validated('method')),
            (string) $this->validated('amount'),
            $this->validated('reference') !== null ? (string) $this->validated('reference') : null,
            $this->validated('notes') !== null ? (string) $this->validated('notes') : null,
            $user !== null ? (int) $user->getAuthIdentifier() : null,
            $this->boolean('security_deposit'),
        );
    }

    /**
     * Where to go afterwards: a page of this application (e.g. the check-in screen), else null.
     */
    public function returnTo(): ?string
    {
        $url = $this->validated('return_to');

        return is_string($url) && str_starts_with($url, $this->getSchemeAndHttpHost().'/') ? $url : null;
    }
}
