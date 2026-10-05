<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Signing in on a POS terminal: who and their PIN (PosStaff and CheckPosPin decide).
 */
class PosSignInRequest extends FormRequest
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
            'user_id' => ['required', 'integer', TenantRule::exists('users')],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }
}
