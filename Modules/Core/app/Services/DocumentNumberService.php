<?php

namespace Modules\Core\Services;

use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Models\DocumentSequence;

class DocumentNumberService implements DocumentNumbers
{
    /**
     * @var array<string, DocumentType>
     */
    private array $types = [];

    public function register(DocumentType $type): void
    {
        if (isset($this->types[$type->key])) {
            throw new InvalidArgumentException("Document type [{$type->key}] is registered twice.");
        }

        $this->types[$type->key] = $type;
    }

    public function type(string $key): DocumentType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown document type [{$key}].");
    }

    public function types(): array
    {
        return $this->types;
    }

    public function next(string $type, ?int $propertyId = null, ?DateTimeInterface $date = null): string
    {
        $definition = $this->type($type);
        $date ??= now();
        $year = (int) $date->format('Y');

        return DB::transaction(function () use ($definition, $propertyId, $date, $year): string {
            $sequence = $this->lockedSequence($definition, $propertyId);

            $number = $sequence->next_number;

            // No period yet means no number has been taken: keep next_number as configured.
            if ($sequence->reset === SequenceReset::Yearly && $sequence->period !== null && $sequence->period !== $year) {
                $number = 1;
            }

            // Query-builder update: taking a number is not an audited change of the sequence settings.
            DocumentSequence::query()->whereKey($sequence->id)->update([
                'next_number' => $number + 1,
                'period' => $year,
                'updated_at' => now(),
            ]);

            return $this->format($sequence->format, $sequence->prefix, $number, $date);
        }, attempts: 5);
    }

    public function format(string $format, string $prefix, int $number, DateTimeInterface $date): string
    {
        $formatted = strtr($format, [
            '{PREFIX}' => $prefix,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
        ]);

        return (string) preg_replace_callback(
            '/\{SEQ(?::(\d{1,2}))?\}/',
            fn (array $match): string => str_pad((string) $number, (int) ($match[1] ?? 0), '0', STR_PAD_LEFT),
            $formatted,
        );
    }

    /**
     * The sequence row, locked for this transaction.
     *
     * Sequences are normally created with the tenant (CreateDocumentSequences), so this is a
     * single locking read of an existing row. A type registered later is created here on first
     * use; if several requests race to create it, the database may report a deadlock, which
     * next()'s transaction retries.
     */
    private function lockedSequence(DocumentType $definition, ?int $propertyId): DocumentSequence
    {
        $find = fn (): ?DocumentSequence => DocumentSequence::query()
            ->where('document_type', $definition->key)
            ->where('property_id', $propertyId)
            ->lockForUpdate()
            ->first();

        $sequence = $find();

        if ($sequence instanceof DocumentSequence) {
            return $sequence;
        }

        try {
            // Savepoint: a concurrent creator makes this insert fail without aborting the outer transaction.
            DB::transaction(fn (): DocumentSequence => $this->create($definition, $propertyId));
        } catch (UniqueConstraintViolationException) {
            // Another request created it first; lock that row instead.
        }

        return $find() ?? throw new InvalidArgumentException("Could not create the [{$definition->key}] sequence.");
    }

    /**
     * Create the sequence row with the type's default numbering (no-op when it exists).
     */
    public function ensure(string $type, ?int $propertyId = null): void
    {
        $definition = $this->type($type);

        if (! DocumentSequence::query()->where('document_type', $type)->where('property_id', $propertyId)->exists()) {
            $this->create($definition, $propertyId);
        }
    }

    private function create(DocumentType $definition, ?int $propertyId): DocumentSequence
    {
        return DocumentSequence::query()->create([
            'property_id' => $propertyId,
            'document_type' => $definition->key,
            'prefix' => $definition->prefix,
            'format' => $definition->format,
            'next_number' => 1,
            'reset' => $definition->reset,
        ]);
    }
}
