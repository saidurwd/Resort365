<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening a POS session: the cash float in the drawer.
 */
class OpenPosSessionRequest extends FormRequest
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
            'opening_float' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ];
    }
}
