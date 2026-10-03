<?php

namespace Modules\Property\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Http\Requests\Concerns\CottageRules;
use Modules\Property\Models\Cottage;

class UpdateCottageRequest extends FormRequest
{
    use CottageRules;

    public function authorize(): bool
    {
        $cottage = $this->route('cottage');

        return $cottage instanceof Cottage && ($this->user()?->can('update', $cottage) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cottage = $this->route('cottage');

        return $this->cottageRules($cottage instanceof Cottage ? $cottage : null);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCottage();
    }
}
