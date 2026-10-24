<?php

namespace Modules\Website\Http\Requests;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Website\Enums\SiteTheme;
use Modules\Website\Models\WebsiteSite;

/**
 * Website → Site settings (Step 8.2a): the current property's site. Texts come per language (`intro_title[bn]`).
 */
class SaveWebsiteSiteRequest extends FormRequest
{
    /** Web addresses the site may not take (they are the application's own). */
    public const array RESERVED_SLUGS = ['admin', 'api', 'www', 'resort', 'booking', 'login', 'dashboard'];

    public function authorize(): bool
    {
        return $this->user()?->can('website.content.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $site = WebsiteSite::query()->where('property_id', $this->propertyId())->first();
        $rules = [
            'slug' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(self::RESERVED_SLUGS),
                TenantRule::unique('website_sites', 'slug')->ignore($site?->id)],
            'is_published' => ['boolean'],
            'theme' => ['required', Rule::enum(SiteTheme::class)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url:https', 'max:500'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],
        ];

        foreach (WebsiteSite::TEXTS as $text) {
            $rules[$text] = ['nullable', 'array:'.implode(',', array_keys((array) config('website.locales')))];
            $rules[$text.'.*'] = ['nullable', 'string', in_array($text, ['tagline', 'intro_title', 'seo_title'], true) ? 'max:200' : 'max:5000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => __('Use small letters, numbers and dashes, e.g. rodela-cxb.')];
    }

    public function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_published' => $this->boolean('is_published'), 'slug' => strtolower(trim((string) $this->input('slug')))]);
    }
}
