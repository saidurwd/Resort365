<?php

namespace Modules\Guest\Http\Controllers\Api\V1;

use App\Support\Api\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Guest\Actions\SaveGuest;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Http\Requests\GuestIndexRequest;
use Modules\Guest\Http\Requests\SaveGuestRequest;
use Modules\Guest\Http\Resources\GuestResource;
use Modules\Guest\Models\Guest;
use Modules\Guest\Services\DuplicateGuestFinder;
use Modules\Guest\Services\GuestSearch;

/**
 * Guest profiles (Step 8.1).
 *
 * @tags Guest
 */
class GuestController extends Controller
{
    public function __construct(private readonly DuplicateGuestFinder $duplicates) {}

    /**
     * List or search guests.
     *
     * Guests of the company, by name, phone, email or ID number (`q`), newest first.
     */
    public function index(GuestIndexRequest $request, GuestSearch $search): AnonymousResourceCollection
    {
        $guests = $search->apply(Guest::query(), (string) $request->input('q', ''))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', CarbonImmutable::parse((string) $request->input('updated_since'))->utc()))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return GuestResource::collection($guests);
    }

    /**
     * Show a guest.
     */
    public function show(Guest $guest): GuestResource
    {
        return new GuestResource($guest);
    }

    /**
     * Create a guest.
     *
     * A guest with the same phone, email or ID number is a 409 `possible_duplicate` listing them; send `confirm_duplicate: true` to
     * create the guest anyway.
     */
    public function store(SaveGuestRequest $request, SaveGuest $save): JsonResponse
    {
        if (! $request->boolean('confirm_duplicate')) {
            $this->refuseDuplicates($request);
        }

        return new GuestResource($save->handle(null, $request->guestData()))->response()->setStatusCode(201);
    }

    /**
     * Update a guest.
     *
     * Replaces the profile with the fields sent (send them all, as for creating).
     */
    public function update(SaveGuestRequest $request, Guest $guest, SaveGuest $save): GuestResource
    {
        return new GuestResource($save->handle($guest, $request->guestData()));
    }

    private function refuseDuplicates(SaveGuestRequest $request): void
    {
        $phone = $request->string('phone')->toString() ?: null;
        $email = $request->string('email')->toString() ?: null;
        $idType = IdType::tryFrom($request->string('id_type')->toString());
        $idNumber = $request->string('id_number')->toString() ?: null;
        $found = $this->duplicates->find($phone, $email, $idType, $idNumber);

        if ($found->isNotEmpty()) {
            throw new ApiException(409, 'possible_duplicate', __('A guest with the same phone, email or ID number exists. Use that guest, or send confirm_duplicate: true.'), [
                'duplicates' => $found->map(fn (Guest $guest): array => [
                    'id' => $guest->id, 'name' => $guest->full_name, 'matched' => $this->duplicates->matchedOn($guest, $phone, $email, $idType, $idNumber),
                ])->values()->all(),
            ]);
        }
    }
}
