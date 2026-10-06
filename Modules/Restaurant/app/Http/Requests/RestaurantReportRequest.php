<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Services\Reports\RestaurantReports;

/**
 * The filters of a restaurant report: a range of business dates, one outlet, and for sales what to group by.
 */
class RestaurantReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.report.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'outlet' => ['nullable', 'integer', TenantRule::exists('outlets')],
            'by' => ['nullable', Rule::in(RestaurantReports::SALES_BY)],
            'export' => ['nullable', Rule::in(['csv'])],
        ];
    }
}
