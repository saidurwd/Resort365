<?php

namespace Modules\Reports\DTOs;

use App\Support\DTOs\Data;

/**
 * A report the framework can run (Step 7.1): its key (the URL), title, group, the permission to see it, and which filters it
 * uses ("range" dates, "by" booking date or arrival, "horizon" days ahead, "limit" top rows).
 */
final readonly class ReportDefinition extends Data
{
    /**
     * @param  list<string>  $filters
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $group,
        public string $description,
        public string $permission,
        public array $filters,
    ) {}
}
