<?php

namespace Modules\FrontOffice\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\FrontOffice\Models\NightAudit;

/**
 * Running the night audit from the wizard: the business date on screen, so an audit that already
 * moved the date on is not run again for the next day by a second click.
 */
class RunNightAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('run', NightAudit::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
