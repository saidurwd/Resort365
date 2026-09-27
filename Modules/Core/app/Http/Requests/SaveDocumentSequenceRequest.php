<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\SequenceReset;

class SaveDocumentSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.sequence.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/\-]+$/'],
            'format' => ['required', 'string', 'max:100', 'regex:/\{SEQ(:\d{1,2})?\}/'],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'reset' => ['required', Rule::enum(SequenceReset::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['format.regex' => __('The format must contain {SEQ} or {SEQ:n}.')];
    }
}
