<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\JournalInvariants;
use Modules\Core\Contracts\DocumentNumbers;

/**
 * Posts a draft journal entry (ARCHITECTURE §5.14, §7.2): the one way an entry becomes real. It must balance
 * (Σ debit = Σ credit, at least two lines, each a debit or a credit), use postable accounts (active, not a
 * group) and fall in an open fiscal period. It gets the next gap-free number (JV-2026-00001) in the same
 * transaction. From then on it is immutable: only a reversal corrects it.
 */
class PostJournalEntry extends Action
{
    public function __construct(
        private readonly JournalInvariants $invariants,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return $this->transaction(function () use ($entry, $userId): JournalEntry {
            $locked = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($locked->status !== JournalStatus::Draft) {
                throw new AccountingRuleViolated(__('Entry :no is already posted.', ['no' => $locked->entry_no]));
            }

            $lines = JournalLine::query()->where('journal_entry_id', $locked->id)->with('account')->orderBy('line_no')->get();
            $problems = $this->invariants->problems($lines->map(fn (JournalLine $line): array => ['debit' => $line->debit, 'credit' => $line->credit])->all());

            if ($problems !== []) {
                throw new AccountingRuleViolated(implode(' ', $problems));
            }

            foreach ($lines as $line) {
                if ($line->account->is_group || ! $line->account->is_active) {
                    throw new AccountingRuleViolated(__('Line :n: :account cannot take postings (it is :why).', ['n' => $line->line_no, 'account' => $line->account->label(),
                        'why' => $line->account->is_group ? __('a group') : __('inactive')]));
                }
            }

            $date = $locked->entry_date->toDateString();
            $period = FiscalPeriod::query()->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->first();

            if (! $period instanceof FiscalPeriod) {
                throw new AccountingRuleViolated(__('No fiscal period covers :date: create the fiscal year first.', ['date' => $locked->entry_date->format('d M Y')]));
            }

            if (! $period->status->acceptsPostings()) {
                throw new AccountingRuleViolated(__(':period is :status: no posting into it.', ['period' => $period->name, 'status' => mb_strtolower($period->status->label())]));
            }

            $locked->forceFill([
                'entry_no' => $this->numbers->next('journal', null, $locked->entry_date), 'fiscal_period_id' => $period->id, 'status' => JournalStatus::Posted,
                'total' => $this->invariants->total($lines->map(fn (JournalLine $line): array => ['debit' => $line->debit, 'credit' => $line->credit])->all()),
                'posted_by' => $userId, 'posted_at' => now(),
            ])->save();

            return $locked;
        }, attempts: 3);
    }
}
