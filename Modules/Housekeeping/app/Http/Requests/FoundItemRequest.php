<?php

namespace Modules\Housekeeping\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Housekeeping\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Housekeeping\Models\LostFoundItem;

/**
 * Logging a found item at the current property.
 */
class FoundItemRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('create', LostFoundItem::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'found_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'room_id' => ['nullable', 'integer', $this->roomRule()],
            'found_at' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:500'],
            'stored_at' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
