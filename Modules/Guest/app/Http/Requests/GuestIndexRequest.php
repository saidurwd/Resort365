<?php

namespace Modules\Guest\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/v1/guest/guests` (Step 8.1): an optional search term and paging. Permission: `api.can:guest.guest.view`.
 */
class GuestIndexRequest extends FormRequest
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
            /** Name words, phone, email or ID number (prefix search). */
            'q' => ['nullable', 'string', 'max:100'],
            /** Changed at or after this time (ISO 8601), for syncing. */
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
