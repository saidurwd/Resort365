<?php

namespace Modules\Guest\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Guest\Models\Guest;

class BlacklistGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guest = $this->route('guest');

        return $guest instanceof Guest && ($this->user()?->can('blacklist', $guest) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
