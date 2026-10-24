<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayProvider;

/**
 * Setup → Online payments (Step 8.3): one gateway of the current property: sandbox or live, its credentials (empty = keep), on or off.
 */
class SaveGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.gateway.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(GatewayMode::class)],
            'is_enabled' => ['boolean'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function provider(): GatewayProvider
    {
        return GatewayProvider::tryFrom((string) $this->route('provider')) ?? abort(404);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }
}
