<?php

namespace Modules\Guest\Services;

use App\Support\Attachments\Attachments;
use Illuminate\Http\UploadedFile;
use Modules\Guest\Actions\SaveGuest;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestIdentity;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\Enums\VipLevel;
use Modules\Guest\Models\Guest;
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

    public function recordIdentity(GuestIdentity $identity, ?UploadedFile $scan = null, ?int $uploaderId = null, ?string $uploaderName = null): GuestSummary
    {
        $guest = Guest::query()->findOrFail($identity->guestId);

        // SaveGuest normalises phone and email from what it is given, so they go along unchanged.
        $this->save->handle($guest, [
            'phone' => $guest->phone,
            'email' => $guest->email,
            'id_type' => $identity->idType->value,
            'id_number' => $identity->idNumber,
            'id_expiry' => $identity->idExpiry,
            'nationality_code' => $identity->nationalityCode ?? $guest->nationality_code,
        ]);

        if ($scan instanceof UploadedFile) {
            $media = $guest->addMedia($scan)->usingName(__('ID scan :date', ['date' => now()->format('Y-m-d')]))
                ->withCustomProperties(['uploaded_by' => $uploaderId, 'uploaded_by_name' => $uploaderName])
                ->toMediaCollection(Attachments::COLLECTION);
            activity()->performedOn($guest)->event('updated')->log('Attachment "'.$media->file_name.'" added');
        }

        return $this->lookup->find($guest->id) ?? throw new RuntimeException('The guest could not be read back.');
    }
}
