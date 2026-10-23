<?php

namespace Modules\Guest\Http\Resources;

use App\Support\Api\ApiAbility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Guest\Models\Guest;

/**
 * A guest profile as the API shows it (Step 8.1). The ID number is masked unless the token may use `guest.guest.view-id`.
 *
 * @mixin Guest
 */
class GuestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $idNumber = $this->id_number;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nationality_code' => $this->nationality_code,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'id_type' => $this->id_type?->value,
            'id_number' => $idNumber === null || ApiAbility::allows($request->user(), 'guest.guest.view-id') ? $idNumber : str_repeat('•', max(0, mb_strlen($idNumber) - 4)).mb_substr($idNumber, -4),
            'id_expiry' => $this->id_expiry?->toDateString(),
            'address' => $this->address,
            'company_id' => $this->company_id,
            'vip_level' => $this->vip_level->value,
            'is_blacklisted' => $this->is_blacklisted,
            'preferences' => $this->preferences ?? [],
            'marketing_consent' => $this->marketing_consent,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
