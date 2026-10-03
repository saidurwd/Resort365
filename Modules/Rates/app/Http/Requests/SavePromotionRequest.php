<?php

namespace Modules\Rates\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Rates\Enums\DiscountType;
use Modules\Rates\Http\Requests\Concerns\UsesCurrentProperty;
use Modules\Rates\Models\Promotion;

class SavePromotionRequest extends FormRequest
{
    use UsesCurrentProperty;

    public function authorize(): bool
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion
            ? ($this->user()?->can('update', $promotion) ?? false)
            : ($this->user()?->can('create', Promotion::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $promotion = $this->route('promotion');
        $promotion = $promotion instanceof Promotion ? $promotion : null;
        $propertyId = $this->propertyId($promotion);

        return [
            'code' => ['nullable', 'string', 'max:30', 'alpha_dash:ascii', TenantRule::unique('promotions', 'code')->where('property_id', $propertyId)->ignore($promotion?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'decimal:0,2', 'gt:0', $this->input('discount_type') === DiscountType::Percent->value ? 'max:100' : 'max:9999999999999'],
            'stay_from' => ['nullable', 'date_format:Y-m-d'],
            'stay_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:stay_from'],
            'book_from' => ['nullable', 'date_format:Y-m-d'],
            'book_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:book_from'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_nights' => ['nullable', 'integer', 'min:1', 'max:365', 'gte:min_nights'],
            'min_advance_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'rate_plan_ids' => ['nullable', 'array'],
            'rate_plan_ids.*' => ['integer', TenantRule::exists('rate_plans')->where('property_id', $propertyId)->withoutTrashed()],
            'unit_keys' => ['nullable', 'array'],
            'unit_keys.*' => ['string'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $promotion = $this->route('promotion');
            $known = $this->unitTypes($this->propertyId($promotion instanceof Promotion ? $promotion : null));

            if (array_diff((array) $this->input('unit_keys', []), array_keys($known)) !== []) {
                $validator->errors()->add('unit_keys', __('Unknown room or cottage type.'));
            }
        }];
    }

    /**
     * Validated fields with empty scope lists stored as null ("any").
     *
     * @return array<string, mixed>
     */
    public function promotionData(): array
    {
        $data = $this->validated();
        $data['rate_plan_ids'] = array_values(array_map(intval(...), (array) ($data['rate_plan_ids'] ?? []))) ?: null;
        $data['unit_keys'] = array_values((array) ($data['unit_keys'] ?? [])) ?: null;

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));

        $this->merge(['code' => $code === '' ? null : $code, 'is_active' => ! $this->has('is_active') || $this->boolean('is_active')]);
    }
}
