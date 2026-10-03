<?php

namespace Modules\Guest\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Guest\Models\TravelAgent;

class SaveTravelAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = $this->route('travel_agent');

        return $agent instanceof TravelAgent
            ? ($this->user()?->can('update', $agent) ?? false)
            : ($this->user()?->can('create', TravelAgent::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $agent = $this->route('travel_agent');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', TenantRule::unique('travel_agents', 'code')->ignore($agent instanceof TravelAgent ? $agent->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'array:line1,line2,city,state,postal_code,country_code'],
            'address.*' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'credit_limit' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'commission_percent' => $this->input('commission_percent') ?: '0',
            'credit_limit' => $this->input('credit_limit') ?: '0',
            'is_active' => ! $this->has('is_active') || $this->boolean('is_active'),
        ]);
    }
}
