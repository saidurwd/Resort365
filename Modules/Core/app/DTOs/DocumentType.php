<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Modules\Core\Enums\SequenceReset;

/**
 * A numbered document type a module registers, with its default numbering.
 * Format tokens: {PREFIX} {YYYY} {YY} {MM} {DD} {SEQ} or {SEQ:n} (zero-padded to n digits).
 */
final readonly class DocumentType extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $prefix,
        public string $format = '{PREFIX}-{YYYY}-{SEQ:5}',
        public SequenceReset $reset = SequenceReset::Yearly,
    ) {}
}
