<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\PrinterConnection;
use Modules\Restaurant\Enums\PrinterType;
use Modules\Restaurant\Models\Printer;

/**
 * A printer of the current property.
 */
class PrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($printer = $this->route('printer')) instanceof Printer ? ($this->user()?->can('update', $printer) ?? false) : ($this->user()?->can('create', Printer::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(PrinterType::class)],
            'connection' => ['required', Rule::enum(PrinterConnection::class)],
            'address' => ['nullable', 'string', 'max:190'],
            'paper_width_mm' => ['required', 'integer', 'in:58,80'],
            'is_active' => ['boolean'],
        ];
    }
}
