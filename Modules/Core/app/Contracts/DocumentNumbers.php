<?php

namespace Modules\Core\Contracts;

use DateTimeInterface;
use Modules\Core\DTOs\DocumentType;

/**
 * Gap-free document numbers per tenant, document type and (optionally) property
 * (ARCHITECTURE §5.1, §9.2). Call next() inside the transaction that saves the document,
 * so a rolled-back save also gives the number back.
 */
interface DocumentNumbers
{
    public function register(DocumentType $type): void;

    public function type(string $key): DocumentType;

    /**
     * @return array<string, DocumentType>
     */
    public function types(): array;

    /**
     * Take the next number, e.g. "RSV-2026-00042". Safe under concurrency (row lock). When called
     * inside your own transaction, give it retry attempts (DB::transaction($fn, 3)): a lock
     * conflict rolls back the whole transaction.
     */
    public function next(string $type, ?int $propertyId = null, ?DateTimeInterface $date = null): string;

    /**
     * Create the sequence with the type's default numbering if it does not exist yet.
     */
    public function ensure(string $type, ?int $propertyId = null): void;

    /**
     * Render a number with the given format (used for previews).
     */
    public function format(string $format, string $prefix, int $number, DateTimeInterface $date): string;
}
