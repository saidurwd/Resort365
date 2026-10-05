<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\Course;

/**
 * Changing a line not yet sent (JSON): only the fields given change.
 */
class ChangeOrderLineRequest extends FormRequest
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
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'seat' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'course' => ['sometimes', Rule::enum(Course::class)],
            'held' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:300'],
        ];
    }
}
