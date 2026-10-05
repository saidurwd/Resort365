<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\DiscountType;

/**
 * A discount on an item (line_id) or the whole bill (JSON); no type takes it off.
 */
class BillDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.order.take') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'integer', TenantRule::exists('pos_order_lines')],
            'type' => ['nullable', Rule::enum(DiscountType::class)],
            'value' => ['nullable', 'required_with:type', 'numeric', 'min:0', 'max:9999999'],
            'reason' => ['nullable', 'required_with:type', 'string', 'max:300'],
            'approval_id' => ['nullable', 'integer'],
        ];
    }
}
