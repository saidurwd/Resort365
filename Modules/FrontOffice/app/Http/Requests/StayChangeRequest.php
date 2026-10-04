<?php

namespace Modules\FrontOffice\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A stay change: a room move (item and room) or a new departure date.
 */
class StayChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('frontoffice.stay.change') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('frontoffice.stay.move')) {
            return [
                'reservation_item_id' => ['required', 'integer', TenantRule::exists('reservation_items')->where('reservation_id', (int) $this->route('reservation'))],
                'room_id' => ['required', 'integer', TenantRule::exists('rooms')->withoutTrashed()],
                'reprice' => ['boolean'],
            ];
        }

        return ['check_out' => ['required', 'date_format:Y-m-d']];
    }
}
