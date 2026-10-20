<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Opens a payroll run (Step 6.5) for a property and month, numbered `PRUN-2026-00001`. There is one run per property and
 * month, and not for a month that has not begun. `include_annual` lists the annual components (festival bonuses) paid with
 * this run.
 */
class CreatePayrollRun extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @param  list<string>  $includeAnnual
     *
     * @throws PayrollRuleViolated
     */
    public function handle(int $propertyId, string $month, array $includeAnnual, ?string $notes, ?int $userId): PayrollRun
    {
        $property = $this->properties->find($propertyId) ?? throw new PayrollRuleViolated(__('Choose a property you can work in.'));

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new PayrollRuleViolated(__('Choose a month.'));
        }

        $period = CarbonImmutable::parse($month.'-01');

        if ($period->gt(CarbonImmutable::parse($property->businessDate)->startOfMonth())) {
            throw new PayrollRuleViolated(__('A payroll run cannot be made for a month that has not begun.'));
        }

        return $this->transaction(function () use ($propertyId, $period, $includeAnnual, $notes, $userId): PayrollRun {
            if (PayrollRun::query()->where('property_id', $propertyId)->whereDate('period', $period->toDateString())->exists()) {
                throw new PayrollRuleViolated(__('There is already a payroll run for :month.', ['month' => $period->format('F Y')]));
            }

            return PayrollRun::query()->create([
                'property_id' => $propertyId, 'run_no' => $this->numbers->next('payroll_run', $propertyId, $period), 'period' => $period->toDateString(), 'status' => RunStatus::Draft,
                'include_annual' => array_values(array_unique(array_map(strtoupper(...), $includeAnnual))) ?: null, 'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null, 'created_by' => $userId,
            ]);
        }, attempts: 3);
    }
}
