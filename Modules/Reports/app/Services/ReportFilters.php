<?php

namespace Modules\Reports\Services;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Modules\Reports\DTOs\ReportDefinition;
use Modules\Reports\DTOs\ReportFilter;

/**
 * Turns what was asked on a report page into a `ReportFilter` (Step 7.1): the property (this one, or "all" for a consolidated
 * report), the dates, and the options the report uses. A consolidated report covers the properties the user may see that share the
 * current property's currency, because amounts in different currencies cannot be added.
 */
class ReportFilters
{
    public function __construct(
        private readonly PropertyContext $context,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function make(ReportDefinition $definition, array $input): ReportFilter
    {
        $scope = $this->scope((string) ($input['property'] ?? ''));
        $today = CarbonImmutable::parse($scope['today']);
        $defaultBy = $definition->group === 'guests' ? 'arrival' : 'booked';

        if (in_array('horizon', $definition->filters, true)) {
            $horizon = in_array((int) ($input['horizon'] ?? 30), [30, 60, 90], true) ? (int) ($input['horizon'] ?? 30) : 30;
            $from = $today->toDateString();
            $to = $today->addDays($horizon - 1)->toDateString();
        } else {
            $from = (string) ($input['from'] ?? $today->startOfMonth()->toDateString());
            $to = (string) ($input['to'] ?? $today->toDateString());
        }

        return new ReportFilter($scope['ids'], $from, $to, in_array($input['by'] ?? $defaultBy, ['booked', 'arrival'], true) ? (string) ($input['by'] ?? $defaultBy) : $defaultBy, max(1, min(100, (int) ($input['limit'] ?? 10))), $scope['currency']);
    }

    /**
     * The properties a report may cover, and the business date. Without any property (a new tenant that has not set one up) the
     * answer has no ids, which the dashboard shows as its welcome page.
     *
     * @return array{ids: list<int>, today: string, currency: string, names: array<int, string>}
     */
    public function dashboardScope(string $property): array
    {
        return $this->resolve($property, false);
    }

    /**
     * The properties a report may cover, and the business date; a report needs at least one property.
     *
     * @return array{ids: list<int>, today: string, currency: string, names: array<int, string>}
     */
    public function scope(string $property): array
    {
        return $this->resolve($property, true);
    }

    /**
     * @return array{ids: list<int>, today: string, currency: string, names: array<int, string>}
     */
    private function resolve(string $property, bool $required): array
    {
        $accessible = array_values(array_filter($this->properties->all(), fn (PropertySummary $p): bool => $p->isActive && $this->context->canAccess($p->id)));
        $current = $this->context->currentId();
        $base = null;

        foreach ($accessible as $candidate) {
            if ($candidate->id === $current) {
                $base = $candidate;
            }
        }

        usort($accessible, fn (PropertySummary $a, PropertySummary $b): int => $a->id <=> $b->id);
        $base ??= $accessible[0] ?? null;

        if (! $base instanceof PropertySummary) {
            if ($required) {
                abort(403, __('Choose a property first.'));
            }

            return ['ids' => [], 'today' => now()->toDateString(), 'currency' => '', 'names' => []];
        }

        $chosen = $property === 'all' ? array_values(array_filter($accessible, fn (PropertySummary $p): bool => $p->currencyCode === $base->currencyCode)) : [$base];

        return [
            'ids' => array_map(fn (PropertySummary $p): int => $p->id, $chosen), 'today' => $base->businessDate, 'currency' => $base->currencyCode,
            'names' => collect($chosen)->mapWithKeys(fn (PropertySummary $p): array => [$p->id => $p->name])->all(),
        ];
    }
}
