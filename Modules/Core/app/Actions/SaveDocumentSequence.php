<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Models\DocumentSequence;

/**
 * Changes the numbering of a document type (tenant level). Input validated by SaveDocumentSequenceRequest.
 */
class SaveDocumentSequence extends Action
{
    public function __construct(private readonly DocumentNumbers $numbers) {}

    public function handle(string $type, string $prefix, string $format, int $nextNumber, SequenceReset $reset, ?int $propertyId = null): DocumentSequence
    {
        $this->numbers->type($type);

        return $this->transaction(function () use ($type, $prefix, $format, $nextNumber, $reset, $propertyId): DocumentSequence {
            $sequence = DocumentSequence::query()->where('document_type', $type)->where('property_id', $propertyId)->lockForUpdate()->first()
                ?? new DocumentSequence(['document_type' => $type, 'property_id' => $propertyId]);

            // The chosen next number applies from now, so the current year counts as started.
            $sequence->fill(['prefix' => $prefix, 'format' => $format, 'next_number' => $nextNumber, 'reset' => $reset, 'period' => (int) now()->format('Y')]);
            $sequence->save();

            return $sequence;
        });
    }
}
