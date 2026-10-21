<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Closure;

/**
 * One step of the setup checklist a new company works through (Step 7.4). A module registers its own steps with `Onboarding`: the
 * route is the screen that does the step, and `done` answers from the module's own data whether it has been done.
 */
final readonly class OnboardingStep extends Data
{
    /**
     * @param  Closure(): bool  $done
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public int $order,
        public string $route,
        public Closure $done,
        public string $module = 'core',
    ) {}
}
