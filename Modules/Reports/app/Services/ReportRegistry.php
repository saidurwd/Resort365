<?php

namespace Modules\Reports\Services;

use InvalidArgumentException;
use Modules\Reports\Contracts\ReportBuilder;
use Modules\Reports\DTOs\ReportDefinition;

/**
 * The reports the framework runs (Step 7.1). A module registers a definition and its builder class in its provider; the
 * screens, the exports and the hub all come from here.
 */
class ReportRegistry
{
    /** @var array<string, ReportDefinition> */
    private array $definitions = [];

    /** @var array<string, class-string<ReportBuilder>> */
    private array $builders = [];

    /**
     * @param  class-string<ReportBuilder>  $builder
     */
    public function register(ReportDefinition $definition, string $builder): void
    {
        $this->definitions[$definition->key] = $definition;
        $this->builders[$definition->key] = $builder;
    }

    /**
     * @return array<string, ReportDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function find(string $key): ?ReportDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function builder(string $key): ReportBuilder
    {
        return app($this->builders[$key] ?? throw new InvalidArgumentException("Unknown report [{$key}]."));
    }
}
