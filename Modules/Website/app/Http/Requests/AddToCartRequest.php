<?php

namespace Modules\Website\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Website\Models\WebsiteSite;

/**
 * Adds a room type or a whole cottage to the website cart (Step 8.2b), with the stay and the party.
 */
class AddToCartRequest extends FormRequest
{
    use StayRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $site = $this->attributes->get('website.site');

        return [
            ...$this->stayRules(true),
            'rate_plan_id' => ['required', 'integer', TenantRule::exists('rate_plans')->where('property_id', $site instanceof WebsiteSite ? $site->property_id : 0)->withoutTrashed()],
            'type' => ['required', 'in:room,cottage'],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->stayMessages();
    }
}
