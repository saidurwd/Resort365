<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;

/**
 * Opens a fiscal year of twelve monthly periods from a start date (ARCHITECTURE §5.14), all open. Years
 * may not overlap.
 */
class CreateFiscalYear extends Action
{
    /**
     * @throws AccountingRuleViolated
     */
    public function handle(string $startsOn, ?string $name = null): FiscalYear
    {
        $start = CarbonImmutable::parse($startsOn)->startOfMonth();
        $end = $start->addMonths(12)->subDay();

        if (FiscalYear::query()->where('starts_on', '<=', $end->toDateString())->where('ends_on', '>=', $start->toDateString())->exists()) {
            throw new AccountingRuleViolated(__('A fiscal year already covers part of :from to :to.', ['from' => $start->format('d M Y'), 'to' => $end->format('d M Y')]));
        }

        return $this->transaction(function () use ($start, $end, $name): FiscalYear {
            $label = $name !== null && trim($name) !== '' ? trim($name) : ($start->month === 1 ? 'FY '.$start->year : 'FY '.$start->year.'/'.$end->format('y'));
            $year = FiscalYear::query()->create(['name' => $label, 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()]);

            for ($number = 1; $number <= 12; $number++) {
                $from = $start->addMonths($number - 1);
                FiscalPeriod::query()->create([
                    'fiscal_year_id' => $year->id, 'name' => $from->format('F Y'), 'number' => $number, 'starts_on' => $from->toDateString(),
                    'ends_on' => $from->endOfMonth()->toDateString(), 'status' => PeriodStatus::Open,
                ]);
            }

            return $year;
        });
    }
}
