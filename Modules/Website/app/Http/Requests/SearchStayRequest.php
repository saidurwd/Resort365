<?php

namespace Modules\Website\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The website's availability search (Step 8.2b, GET /resort/{site}/book). Without dates the page shows the search form only.
 */
class SearchStayRequest extends FormRequest
{
    use StayRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->stayRules(false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->stayMessages();
    }
}
