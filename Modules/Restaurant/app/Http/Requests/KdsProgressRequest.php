<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Services\KotTransitions;

/**
 * A tap on a kitchen display ticket (JSON). Who may tap is settled by EnsureKdsStation (the station's
 * display, or a person with restaurant.kds.use).
 */
class KdsProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(KotTransitions::ACTIONS)],
        ];
    }
}
