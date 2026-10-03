<?php

namespace Modules\Guest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\ReferenceData;
use Modules\Guest\Actions\SaveGuest;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Http\Requests\SaveGuestRequest;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Services\DuplicateGuestFinder;
use Modules\Guest\Services\GuestsTable;

class GuestController extends Controller
{
    public function __construct(
        private readonly ReferenceData $reference,
        private readonly DuplicateGuestFinder $duplicates,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Guest::class);

        $filter = in_array($request->query('filter'), GuestsTable::FILTERS, true) ? (string) $request->query('filter') : 'all';

        return view('guest::guests.index', [
            'columns' => GuestsTable::columns(),
            'filter' => $filter,
            'search' => (string) $request->query('search', ''),
        ]);
    }

    public function data(Request $request, GuestsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Guest::class);

        $filter = in_array($request->query('filter'), GuestsTable::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $search = $request->input('search.value');

        return $table->toJson($filter, is_string($search) ? $search : '');
    }

    /**
     * Guests for pickers (Tom Select remote search): [{value, text}].
     */
    public function search(Request $request, GuestLookup $lookup): JsonResponse
    {
        Gate::authorize('viewAny', Guest::class);

        return response()->json(array_map(fn (GuestSummary $guest): array => [
            'value' => $guest->id,
            'text' => $guest->name.($guest->phone !== null ? ' · '.$guest->phone : '').($guest->isBlacklisted ? ' · '.__('Blacklisted') : ''),
        ], $lookup->search((string) $request->query('q', ''), 15)));
    }

    public function create(): View
    {
        Gate::authorize('create', Guest::class);

        return view('guest::guests.form', $this->formData(null));
    }

    /**
     * Same phone, email or ID as an existing guest: back to the form with a warning,
     * unless the user confirmed these are different people.
     */
    public function store(SaveGuestRequest $request, SaveGuest $save): RedirectResponse
    {
        if (! $request->boolean('confirm_duplicate')) {
            $duplicates = $this->duplicatesFor($request);

            if ($duplicates !== []) {
                return back()->withInput()->with('duplicates', $duplicates);
            }
        }

        $guest = $save->handle(null, $request->guestData());

        return to_route('guest.guests.show', $guest)->with('success', __('Guest ":name" created.', ['name' => $guest->full_name]));
    }

    public function show(Guest $guest): View
    {
        Gate::authorize('view', $guest);

        $duplicates = $this->duplicates->find($guest->phone, $guest->email, $guest->id_type, $guest->id_number, $guest->id);

        return view('guest::guests.show', [
            'guest' => $guest->load('company'),
            'duplicates' => $duplicates,
            'matchedOn' => fn (Guest $duplicate): array => $this->duplicates->matchedOn($duplicate, $guest->phone, $guest->email, $guest->id_type, $guest->id_number),
            'countries' => $this->reference->countries(),
            'history' => app(AuditTrail::class)->for($guest),
        ]);
    }

    public function edit(Guest $guest): View
    {
        Gate::authorize('update', $guest);

        return view('guest::guests.form', $this->formData($guest));
    }

    public function update(SaveGuestRequest $request, Guest $guest, SaveGuest $save): RedirectResponse
    {
        $save->handle($guest, $request->guestData());

        return to_route('guest.guests.show', $guest)->with('success', __('Guest ":name" saved.', ['name' => $guest->full_name]));
    }

    /**
     * @return list<array{id: int, name: string, phone: string|null, email: string|null, matched: list<string>, url: string}>
     */
    private function duplicatesFor(SaveGuestRequest $request): array
    {
        $phone = $request->string('phone')->toString() ?: null;
        $email = $request->string('email')->toString() ?: null;
        $idType = IdType::tryFrom($request->string('id_type')->toString());
        $idNumber = $request->string('id_number')->toString() ?: null;

        return $this->duplicates->find($phone, $email, $idType, $idNumber)
            ->map(fn (Guest $guest): array => [
                'id' => $guest->id,
                'name' => $guest->full_name,
                'phone' => $guest->phone,
                'email' => $guest->email,
                'matched' => $this->duplicates->matchedOn($guest, $phone, $email, $idType, $idNumber),
                'url' => route('guest.guests.show', $guest),
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Guest $guest): array
    {
        return [
            'guest' => $guest,
            'countries' => $this->reference->countries(),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'history' => $guest instanceof Guest ? app(AuditTrail::class)->for($guest) : [],
        ];
    }
}
