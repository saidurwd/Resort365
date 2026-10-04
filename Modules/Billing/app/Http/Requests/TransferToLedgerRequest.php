<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferToLedgerRequest extends FormRequest
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
            'company_id' => ['required', 'integer', TenantRule::exists('companies')->where('is_active', true)->withoutTrashed()],
            'return_to' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function returnTo(): ?string
    {
        $url = $this->validated('return_to');

        return is_string($url) && str_starts_with($url, $this->getSchemeAndHttpHost().'/') ? $url : null;
    }
}
