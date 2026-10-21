<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * Where a company is in the setup checklist (Step 7.4): every step with whether it is done, and the first one still open.
 */
final readonly class OnboardingProgress extends Data
{
    /**
     * @param  list<array{key: string, label: string, description: string, route: string, done: bool}>  $steps
     */
    public function __construct(
        public array $steps,
        public bool $dismissed,
    ) {}

    public function total(): int
    {
        return count($this->steps);
    }

    public function doneCount(): int
    {
        return count(array_filter($this->steps, fn (array $step): bool => $step['done']));
    }

    public function complete(): bool
    {
        return $this->doneCount() === $this->total();
    }

    /**
     * @return array{key: string, label: string, description: string, route: string, done: bool}|null
     */
    public function next(): ?array
    {
        foreach ($this->steps as $step) {
            if (! $step['done']) {
                return $step;
            }
        }

        return null;
    }

    /** Whether to remind the owner on the dashboard: steps left and the checklist not put away. */
    public function pending(): bool
    {
        return ! $this->dismissed && ! $this->complete();
    }
}
