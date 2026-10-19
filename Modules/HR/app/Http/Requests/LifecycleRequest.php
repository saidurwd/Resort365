<?php

namespace Modules\HR\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\DisciplinaryAction;
use Modules\HR\Enums\ExitReason;

/**
 * One request for the six lifecycle forms; the route's `event` decides the fields.
 */
class LifecycleRequest extends FormRequest
{
    public const array EVENTS = ['confirm', 'transfer', 'promote', 'increment', 'disciplinary', 'exit'];

    public function authorize(): bool
    {
        return in_array($this->route('event'), self::EVENTS, true) && ($this->user()?->can('hr.lifecycle.manage') ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $base = ['effective_on' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000']];

        return match ($this->route('event')) {
            'transfer' => ['property_id' => ['required', 'integer'], 'department_id' => ['nullable', 'integer', TenantRule::exists('departments')]],
            'promote' => ['designation_id' => ['required', 'integer', TenantRule::exists('designations')], 'grade_id' => ['nullable', 'integer', TenantRule::exists('grades')]],
            'increment' => ['previous_amount' => ['required', 'numeric', 'min:0', 'max:99999999'], 'new_amount' => ['required', 'numeric', 'gt:0', 'max:99999999']],
            'disciplinary' => ['action' => ['required', Rule::enum(DisciplinaryAction::class)], 'notes' => ['required', 'string', 'max:1000']],
            'exit' => ['reason' => ['required', Rule::enum(ExitReason::class)]],
            default => [],
        } + $base;
    }
}
