<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Models\Guest;
use Modules\Guest\Services\GuestSearch;
use Modules\Guest\Services\IdNumberHasher;
use Modules\Guest\Services\PhoneNumber;

/**
 * Creates or updates a guest (validated by SaveGuestRequest). Stores the phone in international
 * format and the email in lower case, and keeps id_number_hash in step with the encrypted ID number.
 * When editing, an empty ID number keeps the stored one (the form never shows it in full).
 */
class SaveGuest extends Action
{
    public function __construct(
        private readonly PhoneNumber $phones,
        private readonly IdNumberHasher $hasher,
        private readonly GuestSearch $search,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Guest $guest, array $data): Guest
    {
        $guest ??= new Guest;

        $data['phone'] = $this->phones->normalize(isset($data['phone']) ? (string) $data['phone'] : null, $this->search->callingCode());
        $data['email'] = isset($data['email']) && $data['email'] !== '' ? strtolower(trim((string) $data['email'])) : null;

        $idType = isset($data['id_type']) && $data['id_type'] !== '' ? IdType::from((string) $data['id_type']) : null;
        $idNumber = isset($data['id_number']) && trim((string) $data['id_number']) !== '' ? trim((string) $data['id_number']) : null;

        if (! $idType instanceof IdType) {
            $data['id_number'] = null;
        } elseif ($idNumber === null && $guest->exists) {
            $idNumber = $guest->id_number;
            unset($data['id_number']);
        }

        $guest->fill($data);
        $guest->id_number_hash = $idType instanceof IdType && $idNumber !== null ? $this->hasher->hash($idType, $idNumber) : null;
        $guest->save();

        return $guest;
    }
}
