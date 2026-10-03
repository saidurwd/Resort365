<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * The result of a tax calculation: net + taxTotal = gross, exactly (decimal strings, 2 places).
 */
final readonly class TaxBreakdown extends Data
{
    /**
     * @param  list<TaxLine>  $taxes
     */
    public function __construct(
        public string $net,
        public array $taxes,
        public string $taxTotal,
        public string $gross,
    ) {}
}
