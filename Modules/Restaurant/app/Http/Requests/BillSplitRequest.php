<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\SplitMode;

/**
 * How to print an order's bill (JSON): one bill, or split by item (assignments: line id => [bill index =>
 * units]), by seat, equally (bills) or by amounts.
 */
class BillSplitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.order.take') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(SplitMode::class)],
            'bills' => ['nullable', 'integer', 'min:2', 'max:20'],
            'amounts' => ['nullable', 'array', 'max:20'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
            'assignments' => ['nullable', 'array'],
            'assignments.*' => ['array'],
            'assignments.*.*' => ['integer', 'min:0', 'max:99'],
        ];
    }

    /**
     * @return array{bills?: int, amounts?: list<string>, assignments?: array<int, array<int, int>>}
     */
    public function options(): array
    {
        return array_filter([
            'bills' => $this->filled('bills') ? (int) $this->validated('bills') : null,
            'amounts' => array_values(array_map(strval(...), array_filter((array) $this->validated('amounts', []), fn ($amount): bool => $amount !== null && $amount !== ''))),
            'assignments' => array_map(fn (array $bills): array => array_map(intval(...), $bills), (array) $this->validated('assignments', [])),
        ], fn (int|array|null $value): bool => $value !== null && $value !== []);
    }
}
