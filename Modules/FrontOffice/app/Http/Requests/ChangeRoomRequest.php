<?php

namespace Modules\FrontOffice\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeRoomRequest extends FormRequest
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
            'reservation_item_id' => ['required', 'integer', TenantRule::exists('reservation_items')->where('reservation_id', (int) $this->route('reservation'))],
            'room_id' => ['required', 'integer', TenantRule::exists('rooms')->withoutTrashed()],
        ];
    }
}
