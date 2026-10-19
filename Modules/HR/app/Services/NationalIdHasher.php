<?php

namespace Modules\HR\Services;

/**
 * A keyed hash of a national ID, so duplicates can be found while the number is stored encrypted. Spaces, dashes and
 * letter case are ignored. The key comes from the application key; rotating APP_KEY means re-hashing.
 */
class NationalIdHasher
{
    public function __construct(private readonly string $key) {}

    public function hash(string $number): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $number));

        return $normalized === '' ? null : hash_hmac('sha256', 'national-id|'.$normalized, $this->key);
    }
}
