<?php

namespace Modules\Housekeeping\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Housekeeping\Enums\LostItemStatus;
use Modules\Housekeeping\Models\LostFoundItem;

/**
 * Closing a lost & found entry: returned (to a guest profile or a named person) or disposed of.
 */
class CloseFoundItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($item = $this->route('item')) instanceof LostFoundItem && ($this->user()?->can('update', $item) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in([LostItemStatus::Claimed->value, LostItemStatus::Disposed->value])],
            'guest_id' => ['nullable', 'integer', TenantRule::exists('guests')->withoutTrashed()],
            'claimed_by_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
