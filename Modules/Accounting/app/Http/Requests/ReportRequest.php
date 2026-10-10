<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\DTOs\ReportFilter;
use Modules\Property\Contracts\PropertyDirectory;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.report.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'property' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== 'none' && (! ctype_digit((string) $value) || ! app(PropertyDirectory::class)->find((int) $value))) {
                    $fail(__('Choose one of your properties.'));
                }
            }],
            'department' => ['nullable', 'integer'],
            'account' => ['nullable', 'integer', TenantRule::exists('accounts')],
            'party_type' => ['nullable', 'in:guest,company,travel_agent,vendor,employee'],
            'party_id' => ['nullable', 'integer'],
            'export' => ['nullable', 'in:xlsx,csv,pdf'],
        ];
    }

    public function filter(): ReportFilter
    {
        $to = (string) ($this->validated('to') ?? $this->validated('as_of') ?? now()->toDateString());
        $from = (string) ($this->validated('from') ?? CarbonImmutable::parse($to)->startOfMonth()->toDateString());

        return new ReportFilter(
            $from, $to, $this->filled('property') ? (string) $this->validated('property') : null, $this->filled('department') ? (int) $this->validated('department') : null,
            $this->filled('party_type') ? (string) $this->validated('party_type') : null, $this->filled('party_id') ? (int) $this->validated('party_id') : null,
        );
    }
}
