<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing a POS session: the cash counted by denomination, a reason for any difference, and a manager's approval for a large one.
 */
class ClosePosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.session.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'count' => ['required', 'array'],
            'count.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variance_reason' => ['nullable', 'string', 'max:500'],
            'approval_id' => ['nullable', 'integer'],
        ];
    }
}
