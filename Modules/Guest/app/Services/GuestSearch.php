<?php

namespace Modules\Guest\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Contracts\Settings;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Models\Guest;

/**
 * Finds guests by phone, email, ID number or name, using the guests table's indexes:
 * phone and email match by prefix, an ID number by its hash, a name by word prefixes
 * ("rah udd" finds Rahim Uddin).
 */
class GuestSearch
{
    public function __construct(
        private readonly PhoneNumber $phones,
        private readonly IdNumberHasher $hasher,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  Builder<Guest>  $query
     * @return Builder<Guest>
     */
    public function apply(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if (str_contains($term, '@')) {
            return $query->where('email', 'like', $this->escape(strtolower($term)).'%');
        }

        $idHashes = array_values(array_filter(array_map(fn (IdType $type): ?string => $this->hasher->hash($type, $term), IdType::cases())));

        if (preg_match('/^[+\d][\d\s\-()]{3,}$/', $term) === 1) {
            $phone = (string) $this->phones->normalize($term, $this->callingCode());

            return $query->where(fn (Builder $query) => $query->where('phone', 'like', $this->escape($phone).'%')->orWhereIn('id_number_hash', $idHashes));
        }

        return $query->where(function (Builder $query) use ($term, $idHashes): void {
            $query->where(function (Builder $query) use ($term): void {
                foreach (preg_split('/\s+/', $term) ?: [] as $word) {
                    $prefix = $this->escape($word).'%';
                    $query->where(fn (Builder $query) => $query->where('first_name', 'like', $prefix)->orWhere('last_name', 'like', $prefix));
                }
            })->orWhereIn('id_number_hash', $idHashes);
        });
    }

    public function callingCode(): string
    {
        return (string) $this->settings->get('guest.default_calling_code');
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
