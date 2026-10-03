<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Models\Property;

class UpdatePropertyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('property.access.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'users' => ['required', 'array'],
            'users.*' => ['integer', TenantRule::exists('users')],
            'access' => ['array'],
            'access.*' => ['array'],
            'access.*.*' => ['integer', TenantRule::exists('users')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $propertyIds = array_map(intval(...), array_keys((array) $this->input('access', [])));
            $valid = Property::query()->whereKey($propertyIds)->count();

            if ($valid !== count($propertyIds)) {
                $validator->errors()->add('access', __('Unknown property.'));
            }
        });
    }
}
