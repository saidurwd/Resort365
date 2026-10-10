<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;

/**
 * Closes, locks or reopens a fiscal period (ARCHITECTURE §5.14): open → closed → locked, and back to open.
 * Closing needs the previous month closed too (no gaps), and no draft entries dated in the period;
 * reopening needs accounting.period.reopen and a reason, and a period cannot reopen while a later one is
 * closed. The change is in the audit log.
 */
class ChangePeriodStatus extends Action
{
    /**
     * @throws AccountingRuleViolated
     */
    public function handle(FiscalPeriod $period, PeriodStatus $to, ?int $userId = null, bool $mayReopen = false, ?string $reason = null): FiscalPeriod
    {
        return $this->transaction(function () use ($period, $to, $userId, $mayReopen, $reason): FiscalPeriod {
            $locked = FiscalPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if ($to === PeriodStatus::Open) {
                if (! $mayReopen) {
                    throw new AccountingRuleViolated(__('Reopening a period needs the permission to reopen periods.'));
                }

                if (trim((string) $reason) === '') {
                    throw new AccountingRuleViolated(__('Say why the period is reopened.'));
                }

                if (FiscalPeriod::query()->where('starts_on', '>', $locked->starts_on)->whereIn('status', [PeriodStatus::Closed->value, PeriodStatus::Locked->value])->exists()) {
                    throw new AccountingRuleViolated(__('Reopen the later closed periods first.'));
                }
            } elseif ($from === PeriodStatus::Open && $to === PeriodStatus::Closed) {
                if (JournalEntry::query()->where('fiscal_period_id', $locked->id)->where('status', JournalStatus::Draft->value)->exists()
                    || JournalEntry::query()->where('status', JournalStatus::Draft->value)->whereBetween('entry_date', [$locked->starts_on, $locked->ends_on])->exists()) {
                    throw new AccountingRuleViolated(__('There are draft entries dated in :period: post or discard them first.', ['period' => $locked->name]));
                }

                $earlierOpen = FiscalPeriod::query()->where('ends_on', '<', $locked->starts_on)->where('status', PeriodStatus::Open->value)->orderBy('starts_on')->first();

                if ($earlierOpen instanceof FiscalPeriod) {
                    throw new AccountingRuleViolated(__('Close :period first.', ['period' => $earlierOpen->name]));
                }
            } elseif ($from === PeriodStatus::Open) {
                // Open to locked: close it first.
                throw new AccountingRuleViolated(__('Close the period before locking it.'));
            }

            $locked->forceFill(['status' => $to, 'closed_at' => $to === PeriodStatus::Open ? null : now(), 'closed_by' => $to === PeriodStatus::Open ? null : $userId])->save();

            if ($to === PeriodStatus::Open) {
                activity()->performedOn($locked)->withProperties(['reason' => trim((string) $reason), 'from' => $from->value])->log('reopened');
            }

            return $locked;
        });
    }
}
