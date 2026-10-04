<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * Copying another outlet's price list into this one, with a % change.
 */
class CopyPriceListRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.price.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_outlet_id' => ['required', 'integer', TenantRule::exists('outlets')->where('property_id', $this->propertyId())],
            'percent' => ['nullable', 'decimal:0,2', 'min:-90', 'max:500'],
            'round_whole' => ['boolean'],
            'overwrite' => ['boolean'],
        ];
    }
}
