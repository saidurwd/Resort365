<?php

namespace Modules\Core\Services;

use Closure;
use Modules\Core\Contracts\UsageMeters;

class UsageMeterRegistry implements UsageMeters
{
    /** @var array<string, array{label: string, unit: string, measure: Closure(): (int|float)}> */
    private array $meters = [];

    public function register(string $name, string $label, string $unit, Closure $measure): void
    {
        $this->meters[$name] = ['label' => $label, 'unit' => $unit, 'measure' => $measure];
    }

    public function all(): array
    {
        return array_map(fn (array $meter): array => ['label' => $meter['label'], 'unit' => $meter['unit']], $this->meters);
    }

    public function measure(string $name): int|float
    {
        return isset($this->meters[$name]) ? ($this->meters[$name]['measure'])() : 0;
    }
}
