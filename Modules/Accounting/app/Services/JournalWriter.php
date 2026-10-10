<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PartyType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\DTOs\TravelAgentSummary;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\DepartmentSummary;
use Modules\Property\DTOs\PropertySummary;

/**
 * Writes a journal entry's header and lines as a draft (ARCHITECTURE §5.14): the one place entries are
 * built, for the manual screens, reversals and (Step 4.2) automatic postings. It checks what a draft
 * must satisfy at once — real accounts, valid dimensions — and leaves balance, open period and numbering
 * to PostJournalEntry. Lines are replaced as a whole.
 */
class JournalWriter
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly DepartmentDirectory $departments,
        private readonly GuestLookup $guests,
        private readonly JournalInvariants $invariants,
    ) {}

    /**
     * @param  array{entry_date: string, description: string, reference?: string|null, source_type?: string|null, source_id?: int|null, source_event?: string|null, reverses_id?: int|null}  $header
     * @param  list<array{account_id: int, debit?: string|null, credit?: string|null, description?: string|null, property_id?: int|null, department_id?: int|null, party_type?: string|null, party_id?: int|null}>  $lines
     *
     * @throws AccountingRuleViolated
     */
    public function saveDraft(?JournalEntry $entry, array $header, array $lines, ?int $userId = null): JournalEntry
    {
        if ($entry instanceof JournalEntry && ! $entry->isDraft()) {
            throw new AccountingRuleViolated(__('A posted entry cannot be changed: reverse it instead.'));
        }

        if (trim($header['description']) === '') {
            throw new AccountingRuleViolated(__('Describe the entry.'));
        }

        $rows = $this->normalise($lines);
        $accounts = Account::query()->whereIn('id', array_column($rows, 'account_id'))->pluck('id')->all();

        foreach ($rows as $index => $row) {
            if (! in_array($row['account_id'], $accounts, true)) {
                throw new AccountingRuleViolated(__('Line :n: choose an account of the chart.', ['n' => $index + 1]));
            }

            $this->checkDimensions($row, $index + 1);
        }

        return DB::transaction(function () use ($entry, $header, $rows, $userId): JournalEntry {
            $fields = [
                'entry_date' => $header['entry_date'], 'description' => mb_substr(trim($header['description']), 0, 300), 'reference' => $header['reference'] ?? null,
                'source_type' => $header['source_type'] ?? null, 'source_id' => $header['source_id'] ?? null, 'source_event' => $header['source_event'] ?? null,
                'reverses_id' => $header['reverses_id'] ?? null, 'total' => $this->invariants->total($rows),
            ];

            if ($entry instanceof JournalEntry) {
                $entry->fill($fields)->save();
                $entry->lines()->delete();
            } else {
                $entry = JournalEntry::query()->create([...$fields, 'status' => JournalStatus::Draft, 'created_by' => $userId]);
            }

            foreach ($rows as $index => $row) {
                $entry->lines()->create([...$row, 'line_no' => $index + 1]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{account_id: int, debit: string, credit: string, description: string|null, property_id: int|null, department_id: int|null, party_type: string|null, party_id: int|null}>
     */
    private function normalise(array $lines): array
    {
        return array_map(fn (array $line): array => [
            'account_id' => (int) ($line['account_id'] ?? 0),
            'debit' => $this->amount($line['debit'] ?? null), 'credit' => $this->amount($line['credit'] ?? null),
            'description' => isset($line['description']) && trim((string) $line['description']) !== '' ? mb_substr(trim((string) $line['description']), 0, 300) : null,
            'property_id' => ! empty($line['property_id']) ? (int) $line['property_id'] : null,
            'department_id' => ! empty($line['department_id']) ? (int) $line['department_id'] : null,
            'party_type' => ! empty($line['party_type']) ? (string) $line['party_type'] : null,
            'party_id' => ! empty($line['party_id']) ? (int) $line['party_id'] : null,
        ], $lines);
    }

    private function amount(mixed $value): string
    {
        return $value === null || $value === '' ? '0.00' : number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  array{property_id: int|null, department_id: int|null, party_type: string|null, party_id: int|null}  $row
     *
     * @throws AccountingRuleViolated
     */
    private function checkDimensions(array $row, int $number): void
    {
        if ($row['property_id'] !== null && ! $this->properties->find($row['property_id']) instanceof PropertySummary) {
            throw new AccountingRuleViolated(__('Line :n: choose a property of this company.', ['n' => $number]));
        }

        if ($row['department_id'] !== null && ! $this->departments->find($row['department_id']) instanceof DepartmentSummary) {
            throw new AccountingRuleViolated(__('Line :n: choose a department of this company.', ['n' => $number]));
        }

        if ($row['party_type'] === null && $row['party_id'] === null) {
            return;
        }

        $type = PartyType::tryFrom((string) $row['party_type']);

        if (! $type instanceof PartyType || $row['party_id'] === null) {
            throw new AccountingRuleViolated(__('Line :n: choose the kind of party and who it is.', ['n' => $number]));
        }

        $known = match ($type) {
            PartyType::Guest => $this->guests->find($row['party_id']) instanceof GuestSummary,
            PartyType::Company => $this->guests->findCompany($row['party_id']) instanceof CompanySummary,
            PartyType::TravelAgent => $this->guests->findTravelAgent($row['party_id']) instanceof TravelAgentSummary,
            default => true, // TODO(step-5.x, step-6.x): vendors and employees come with Procurement and HR.
        };

        if (! $known) {
            throw new AccountingRuleViolated(__('Line :n: that :type does not exist.', ['n' => $number, 'type' => mb_strtolower($type->label())]));
        }
    }
}
