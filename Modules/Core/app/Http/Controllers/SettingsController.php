<?php

namespace Modules\Core\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Actions\SaveSettings;
use Modules\Core\Contracts\ReferenceData;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;

/**
 * Settings by group, company-wide or for one of the user's properties (?property=ID).
 * In property scope only property-level settings are shown; an empty value inherits.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PropertyContext $properties,
    ) {}

    public function index(Request $request, ReferenceData $reference): View
    {
        $propertyId = $this->propertyFrom($request);
        $groups = $this->groups($propertyId);
        $active = (string) $request->query('group', (string) array_key_first($groups));
        abort_unless(isset($groups[$active]), 404);

        return view('core::settings.index', [
            'groups' => array_keys($groups),
            'active' => $active,
            'propertyId' => $propertyId,
            'properties' => $this->properties->accessible(),
            'definitions' => $groups[$active],
            'values' => collect($groups[$active])->mapWithKeys(fn (SettingDefinition $definition): array => [
                $definition->key => $propertyId === null ? $this->settings->get($definition->key) : $this->settings->stored($definition->key, $propertyId),
            ])->all(),
            'inherited' => $propertyId === null ? [] : collect($groups[$active])->mapWithKeys(fn (SettingDefinition $definition): array => [
                $definition->key => $this->settings->get($definition->key),
            ])->all(),
            'lookups' => [
                'currency' => $reference->currencies(...),
                'country' => $reference->countries(...),
                'timezone' => $reference->timezones(...),
            ],
        ]);
    }

    public function update(Request $request, string $group, SaveSettings $saveSettings): RedirectResponse
    {
        $propertyId = $this->propertyFrom($request);
        abort_unless(isset($this->groups($propertyId)[$group]), 404);

        $saveSettings->handle($group, $request->all(), $propertyId);

        return to_route('core.settings.index', array_filter(['group' => $group, 'property' => $propertyId]))->with('success', __('Settings saved.'));
    }

    /**
     * The property from ?property=, only when the user may access it.
     */
    private function propertyFrom(Request $request): ?int
    {
        $property = $request->input('property');

        if ($property === null || $property === '') {
            return null;
        }

        abort_unless(is_numeric($property) && array_key_exists((int) $property, $this->properties->accessible()), 404);

        return (int) $property;
    }

    /**
     * @return array<string, list<SettingDefinition>>
     */
    private function groups(?int $propertyId): array
    {
        $groups = $this->settings->grouped();

        if ($propertyId === null) {
            return $groups;
        }

        return array_filter(
            array_map(fn (array $definitions): array => array_values(array_filter($definitions, fn (SettingDefinition $d): bool => $d->scope === SettingScope::Property)), $groups),
            fn (array $definitions): bool => $definitions !== [],
        );
    }
}
