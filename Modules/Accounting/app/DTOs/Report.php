<?php

namespace Modules\Accounting\DTOs;

use App\Support\DTOs\Data;

/**
 * A finished report ready to show and export (Step 4.5): a title, header columns and rows of formatted cells.
 * The first cell of a row is its label; the others are figures. A row may link cells to the ledger (drill-down).
 */
final readonly class Report extends Data
{
    /**
     * @param  list<string>  $columns
     * @param  list<array{cells: list<string>, bold?: bool, indent?: int, links?: array<int, string>}>  $rows
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public array $columns,
        public array $rows,
        public array $notes = [],
    ) {}
}
