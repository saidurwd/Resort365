<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\OnboardingProgress;
use Modules\Core\DTOs\OnboardingStep;

/**
 * The setup checklist of a new company (Step 7.4). Modules register the steps they own in their providers; Core shows them in order
 * at /onboarding and on the dashboard until the company has done them or put the checklist away.
 */
interface Onboarding
{
    public function register(OnboardingStep $step): void;

    /**
     * The current tenant's steps, each checked against its data now.
     */
    public function progress(): OnboardingProgress;

    /**
     * Stops reminding the current tenant on the dashboard (the checklist stays at /onboarding).
     */
    public function dismiss(): void;
}
