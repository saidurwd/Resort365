<?php

namespace Modules\Guest\Services;

use Modules\Guest\Actions\SaveGuest;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\Enums\VipLevel;
use RuntimeException;

class GuestRegistryService implements GuestRegistry
{
    public function __construct(
        private readonly SaveGuest $save,
        private readonly GuestLookup $lookup,
    ) {}

    public function register(GuestDetails $details): GuestSummary
    {
        $guest = $this->save->handle(null, [
            'title' => $details->title,
            'first_name' => $details->firstName,
            'last_name' => $details->lastName,
            'phone' => $details->phone,
            'email' => $details->email,
            'nationality_code' => $details->nationalityCode,
            'company_id' => $details->companyId,
            'vip_level' => VipLevel::None->value,
        ]);

        return $this->lookup->find($guest->id) ?? throw new RuntimeException('The new guest could not be read back.');
    }
}
