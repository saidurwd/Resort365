<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\JournalWriter;

/**
 * Corrects a posted entry by reversal (ARCHITECTURE §7.2): a new posted entry with every debit and credit
 * swapped (same accounts and dimensions), dated today or a date in an open period, pointing at the
 * original, which becomes reversed. An entry is reversed once, and a reversal is not itself reversed.
 */
class ReverseJournalEntry extends Action
{
    public function __construct(
        private readonly JournalWriter $writer,
        private readonly PostJournalEntry $post,
    ) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(JournalEntry $entry, ?string $date = null, ?int $userId = null, ?string $reason = null): JournalEntry
    {
        return $this->transaction(function () use ($entry, $date, $userId, $reason): JournalEntry {
            $original = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($original->status !== JournalStatus::Posted) {
                throw new AccountingRuleViolated($original->status === JournalStatus::Reversed
                    ? __('Entry :no was already reversed.', ['no' => $original->entry_no])
                    : __('Only a posted entry can be reversed.'));
            }

            if ($original->reverses_id !== null) {
                throw new AccountingRuleViolated(__('Entry :no is itself a reversal: it cannot be reversed. Post a new entry instead.', ['no' => $original->entry_no]));
            }

            $lines = JournalLine::query()->where('journal_entry_id', $original->id)->orderBy('line_no')->get()->map(fn (JournalLine $line): array => [
                'account_id' => $line->account_id, 'debit' => $line->credit, 'credit' => $line->debit, 'description' => $line->description, 'property_id' => $line->property_id,
                'department_id' => $line->department_id, 'party_type' => $line->party_type?->value, 'party_id' => $line->party_id,
            ])->all();

            $draft = $this->writer->saveDraft(null, [
                'entry_date' => $date ?? now()->toDateString(),
                'description' => __('Reversal of :no', ['no' => $original->entry_no]).': '.$original->description.(trim((string) $reason) !== '' ? ' ('.trim((string) $reason).')' : ''),
                'reference' => $original->entry_no, 'reverses_id' => $original->id,
            ], $lines, $userId);
            $reversal = $this->post->handle($draft, $userId);
            $original->forceFill(['status' => JournalStatus::Reversed, 'reversed_by_id' => $reversal->id])->save();

            return $reversal;
        });
    }
}
