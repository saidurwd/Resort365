<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Actions\SaveSettings;
use Modules\Core\Contracts\Settings;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Timezone;

/**
 * Tenant-level settings by group. TODO(step-0.8): property overrides (property picker).
 */
class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(Request $request): View
    {
        $groups = $this->settings->grouped();
        $active = (string) $request->query('group', (string) array_key_first($groups));
        abort_unless(isset($groups[$active]), 404);

        return view('core::settings.index', [
            'groups' => array_keys($groups),
            'active' => $active,
            'definitions' => $groups[$active],
            'values' => collect($groups[$active])->mapWithKeys(fn ($definition): array => [$definition->key => $this->settings->get($definition->key)])->all(),
            'lookups' => [
                'currency' => fn (): array => Currency::query()->orderBy('code')->get()->mapWithKeys(fn (Currency $c): array => [$c->getKey() => $c->getKey().' — '.$c->getAttribute('name')])->all(),
                'country' => fn (): array => Country::query()->orderBy('name')->pluck('name', 'code')->all(),
                'timezone' => fn (): array => Timezone::query()->orderBy('name')->get()->mapWithKeys(fn (Timezone $t): array => [$t->getKey() => $t->getKey().' (UTC'.$t->getAttribute('utc_offset').')'])->all(),
            ],
        ]);
    }

    public function update(Request $request, string $group, SaveSettings $saveSettings): RedirectResponse
    {
        abort_unless(isset($this->settings->grouped()[$group]), 404);

        $saveSettings->handle($group, $request->all());

        return to_route('core.settings.index', ['group' => $group])->with('success', __('Settings saved.'));
    }
}
