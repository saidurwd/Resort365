<?php

namespace Modules\Guest\Services;

use Modules\Guest\Enums\IdType;

/**
 * A keyed hash of an ID document (type and number), so duplicates can be found while the number
 * itself is stored encrypted. Spaces, dashes and letter case are ignored. The key comes from the
 * application key; rotating APP_KEY means re-hashing (and re-encrypting) guest ID numbers.
 */
class IdNumberHasher
{
    public function __construct(private readonly string $key) {}

    public function hash(IdType $type, string $number): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $number));

        return $normalized === '' ? null : hash_hmac('sha256', $type->value.'|'.$normalized, $this->key);
    }
}
