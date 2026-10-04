<?php

namespace Modules\FrontOffice\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Guest\Enums\IdType;

/**
 * The guest's ID document at check-in, with an optional scan (image or PDF).
 */
class RecordIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('frontoffice.checkin.perform') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_type' => ['required', Rule::enum(IdType::class)],
            'id_number' => ['required', 'string', 'max:50'],
            'id_expiry' => ['nullable', 'date_format:Y-m-d'],
            'nationality_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')],
            'scan' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}
