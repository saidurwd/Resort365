<?php

namespace Modules\Guest\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Models\Guest;

/**
 * Guests that look like the same person: the same phone, email, or ID document.
 */
class DuplicateGuestFinder
{
    public function __construct(
        private readonly PhoneNumber $phones,
        private readonly IdNumberHasher $hasher,
        private readonly GuestSearch $search,
    ) {}

    /**
     * @return Collection<int, Guest>
     */
    public function find(?string $phone, ?string $email, ?IdType $idType, ?string $idNumber, ?int $ignoreId = null): Collection
    {
        $phone = $this->phones->normalize($phone, $this->search->callingCode());
        $email = $email !== null && trim($email) !== '' ? strtolower(trim($email)) : null;
        $idHash = $idType instanceof IdType && $idNumber !== null ? $this->hasher->hash($idType, $idNumber) : null;

        if ($phone === null && $email === null && $idHash === null) {
            return new Collection;
        }

        return Guest::query()
            ->where(function ($query) use ($phone, $email, $idHash): void {
                $query->when($phone !== null, fn ($query) => $query->orWhere('phone', $phone))
                    ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
                    ->when($idHash !== null, fn ($query) => $query->orWhere('id_number_hash', $idHash));
            })
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->orderBy('first_name')->limit(10)->get();
    }

    /**
     * Which of the details a duplicate shares, e.g. ['phone', 'email'].
     *
     * @return list<string>
     */
    public function matchedOn(Guest $duplicate, ?string $phone, ?string $email, ?IdType $idType, ?string $idNumber): array
    {
        $matches = [];

        if ($duplicate->phone !== null && $duplicate->phone === $this->phones->normalize($phone, $this->search->callingCode())) {
            $matches[] = 'phone';
        }

        if ($duplicate->email !== null && $email !== null && $duplicate->email === strtolower(trim($email))) {
            $matches[] = 'email';
        }

        if ($duplicate->id_number_hash !== null && $idType instanceof IdType && $idNumber !== null && $duplicate->id_number_hash === $this->hasher->hash($idType, $idNumber)) {
            $matches[] = 'id';
        }

        return $matches;
    }
}
