<?php

namespace Modules\Reports\DTOs;

use App\Support\DTOs\Data;

/**
 * A report as a table (Step 7.1): the shape `<x-report-table>` and the CSV, Excel and PDF files all use. Everything is already
 * formatted as text.
 */
final readonly class ReportTable extends Data
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     * @param  list<string>|null  $totals
     * @param  list<array{label: string, value: string}>  $summary
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public array $summary = [],
        public ?string $note = null,
    ) {}

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'columns' => $this->columns, 'rows' => $this->rows, 'totals' => $this->totals, 'summary' => $this->summary, 'note' => $this->note];
    }
}
