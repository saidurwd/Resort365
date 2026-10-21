<?php

namespace Modules\Core\Services;

use App\Support\Tenancy\ModuleAccess;
use Modules\Core\Contracts\Onboarding;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\OnboardingProgress;
use Modules\Core\DTOs\OnboardingStep;

/**
 * Holds the registered setup steps (Step 7.4) and works out which are done for the current tenant; a step of a module the tenant's
 * plan leaves out is not shown. The tenant's dismissal of the dashboard reminder is the setting `core.onboarding_dismissed`.
 */
class OnboardingService implements Onboarding
{
    /** @var array<string, OnboardingStep> */
    private array $steps = [];

    public function __construct(private readonly Settings $settings) {}

    public function register(OnboardingStep $step): void
    {
        $this->steps[$step->key] = $step;
    }

    public function progress(): OnboardingProgress
    {
        $modules = app(ModuleAccess::class);
        $steps = collect($this->steps)->filter(fn (OnboardingStep $step): bool => $modules->enabled($step->module))->sortBy(fn (OnboardingStep $step): int => $step->order)->values()
            ->map(fn (OnboardingStep $step): array => ['key' => $step->key, 'label' => __($step->label), 'description' => __($step->description), 'route' => $step->route, 'done' => (bool) ($step->done)()])
            ->all();

        return new OnboardingProgress($steps, (bool) $this->settings->get('core.onboarding_dismissed'));
    }

    public function dismiss(): void
    {
        $this->settings->set('core.onboarding_dismissed', true);
    }
}
