<?php

namespace Modules\Guest\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Guest\Models\Guest;

/**
 * Merge duplicate_id into the guest in the route (the profile that is kept).
 */
class MergeGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guest = $this->route('guest');

        return $guest instanceof Guest && ($this->user()?->can('merge', $guest) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $guest = $this->route('guest');

        return [
            'duplicate_id' => ['required', 'integer', TenantRule::exists('guests')->withoutTrashed(),
                'not_in:'.($guest instanceof Guest ? $guest->id : 0)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['duplicate_id.not_in' => __('Choose two different guests.')];
    }
}
