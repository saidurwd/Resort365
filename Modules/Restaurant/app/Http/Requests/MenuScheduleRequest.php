<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A menu schedule of the outlet: weekdays, a time window and a price adjustment.
 */
class MenuScheduleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', TenantRule::unique('menu_schedules', 'name')->where('outlet_id', $this->outlet()->id)->ignore($this->route('schedule'))],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'price_adjustment_percent' => ['nullable', 'decimal:0,2', 'min:-100', 'max:200'],
            'is_active' => ['boolean'],
        ];
    }
}
