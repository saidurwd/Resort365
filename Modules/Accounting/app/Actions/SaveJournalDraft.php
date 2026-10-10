<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalWriter;

/**
 * Saves a manual journal entry as a draft (ARCHITECTURE §5.14): date, description, reference and lines with
 * their dimensions. A draft need not balance yet; posting checks everything.
 */
class SaveJournalDraft extends Action
{
    public function __construct(
        private readonly JournalWriter $writer,
    ) {}

    /**
     * @param  array{entry_date: string, description: string, reference?: string|null}  $header
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws AccountingRuleViolated
     */
    public function handle(?JournalEntry $entry, array $header, array $lines, ?int $userId = null): JournalEntry
    {
        return $this->writer->saveDraft($entry, ['entry_date' => $header['entry_date'], 'description' => $header['description'], 'reference' => $header['reference'] ?? null], $lines, $userId);
    }
}
