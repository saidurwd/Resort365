<?php

namespace Modules\HR\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One request for the roster's three forms; the route's `action` (assign, clear, copy) decides the fields.
 */
class RosterRequest extends FormRequest
{
    public const array ACTIONS = ['assign', 'clear', 'copy'];

    public function authorize(): bool
    {
        return in_array($this->route('action'), self::ACTIONS, true) && ($this->user()?->can('hr.roster.manage') ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employees = ['employee_ids' => ['required', 'array', 'min:1'], 'employee_ids.*' => ['integer', TenantRule::exists('employees')]];

        return match ($this->route('action')) {
            'assign' => $employees + [
                'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                'shift_id' => ['nullable', 'integer', TenantRule::exists('shifts')], 'weekdays' => ['nullable', 'array'], 'weekdays.*' => ['integer', 'between:0,6'],
            ],
            'clear' => $employees + ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']],
            default => $employees + ['week_start' => ['required', 'date_format:Y-m-d'], 'weeks' => ['required', 'integer', 'between:1,12']],
        };
    }
}
