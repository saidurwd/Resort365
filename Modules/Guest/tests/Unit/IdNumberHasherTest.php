<?php

use Modules\Guest\Enums\IdType;
use Modules\Guest\Services\IdNumberHasher;

it('hashes the same document the same way, ignoring spaces, dashes and case', function (): void {
    $hasher = new IdNumberHasher('test-key');

    expect($hasher->hash(IdType::Passport, 'ab 123-4567'))->toBe($hasher->hash(IdType::Passport, 'AB1234567'))
        ->and($hasher->hash(IdType::Passport, 'AB1234567'))->toHaveLength(64)
        ->and(str_contains((string) $hasher->hash(IdType::Passport, 'AB1234567'), '1234567'))->toBeFalse();
});

it('tells different types, numbers and keys apart', function (): void {
    $hasher = new IdNumberHasher('test-key');

    expect($hasher->hash(IdType::Passport, '1234567890'))->not->toBe($hasher->hash(IdType::NationalId, '1234567890'))
        ->and($hasher->hash(IdType::NationalId, '1234567890'))->not->toBe($hasher->hash(IdType::NationalId, '1234567891'))
        ->and($hasher->hash(IdType::NationalId, '1234567890'))->not->toBe(new IdNumberHasher('other-key')->hash(IdType::NationalId, '1234567890'));
});

it('has no hash for an empty number', function (): void {
    expect(new IdNumberHasher('test-key')->hash(IdType::Passport, ' - '))->toBeNull();
});
