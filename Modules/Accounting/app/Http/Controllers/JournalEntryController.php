<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\DiscardJournalDraft;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\ReverseJournalEntry;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PartyType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\JournalEntryRequest;
use Modules\Accounting\Http\Requests\ReverseEntryRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\JournalEntriesTable;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\Settings;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\DepartmentSummary;
use Modules\Property\DTOs\PropertySummary;

/**
 * Accounting → Journal entries (ARCHITECTURE §5.14): the list, writing a manual entry as a draft, and on
 * the entry's page posting it, reversing it (once posted) or discarding it (while a draft).
 */
class JournalEntryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JournalEntry::class);

        return view('accounting::journals.index', [
            'columns' => JournalEntriesTable::columns(), 'statuses' => collect(JournalStatus::cases())->mapWithKeys(fn (JournalStatus $status): array => [$status->value => $status->label()])->all(),
            'sources' => ['manual' => __('Manual'), 'automatic' => __('Automatic (from operations)')],
            'canCreate' => $request->user()?->can('accounting.journal.create') ?? false,
        ]);
    }

    public function data(Request $request, JournalEntriesTable $table): JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        return $table->toJson($request->query('status'), $request->query('from'), $request->query('to'), $request->query('source'));
    }

    public function create(Settings $settings): View
    {
        Gate::authorize('create', JournalEntry::class);

        return $this->form(null);
    }

    public function store(JournalEntryRequest $request, SaveJournalDraft $save, PostJournalEntry $post): RedirectResponse
    {
        Gate::authorize('create', JournalEntry::class);

        return $this->persist($request, null, $save, $post);
    }

    public function show(JournalEntry $entry, AuditTrail $audit, UserDirectory $users): View
    {
        Gate::authorize('view', $entry);
        $entry->load(['lines.account', 'period', 'reverses', 'reversedBy']);
        $names = collect($users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);
        $properties = collect(app(PropertyDirectory::class)->all())->mapWithKeys(fn (PropertySummary $property): array => [$property->id => $property->name]);
        $departments = collect(app(DepartmentDirectory::class)->all(false))->mapWithKeys(fn (DepartmentSummary $department): array => [$department->id => $department->name]);

        return view('accounting::journals.show', [
            'entry' => $entry, 'names' => $names, 'properties' => $properties, 'departments' => $departments, 'trail' => $audit->for($entry),
            'user' => auth()->user(),
        ]);
    }

    public function edit(JournalEntry $entry): View|RedirectResponse
    {
        Gate::authorize('update', $entry);

        return $entry->isDraft() ? $this->form($entry->load('lines')) : to_route('accounting.journals.show', $entry)->with('error', __('A posted entry cannot be changed: reverse it instead.'));
    }

    public function update(JournalEntryRequest $request, JournalEntry $entry, SaveJournalDraft $save, PostJournalEntry $post): RedirectResponse
    {
        Gate::authorize('update', $entry);

        return $this->persist($request, $entry, $save, $post);
    }

    public function post(JournalEntry $entry, PostJournalEntry $post): RedirectResponse
    {
        Gate::authorize('post', $entry);

        try {
            $posted = $post->handle($entry, (int) auth()->id());
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.journals.show', $posted)->with('success', __('Entry :no posted.', ['no' => $posted->entry_no]));
    }

    public function reverse(ReverseEntryRequest $request, JournalEntry $entry, ReverseJournalEntry $reverse): RedirectResponse
    {
        Gate::authorize('reverse', $entry);

        try {
            $reversal = $reverse->handle($entry, $request->validated('date'), (int) $request->user()?->getAuthIdentifier(), $request->validated('reason'));
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.journals.show', $reversal)->with('success', __('Entry :no reversed by :reversal.', ['no' => $entry->entry_no, 'reversal' => $reversal->entry_no]));
    }

    public function destroy(JournalEntry $entry, DiscardJournalDraft $discard): RedirectResponse
    {
        Gate::authorize('update', $entry);

        try {
            $discard->handle($entry);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.journals.index')->with('success', __('Draft discarded.'));
    }

    private function persist(JournalEntryRequest $request, ?JournalEntry $entry, SaveJournalDraft $save, PostJournalEntry $post): RedirectResponse
    {
        $userId = (int) $request->user()?->getAuthIdentifier();

        try {
            $saved = $save->handle($entry, ['entry_date' => (string) $request->validated('entry_date'), 'description' => (string) $request->validated('description'), 'reference' => $request->validated('reference')],
                array_values((array) $request->validated('lines')), $userId);

            if ($request->validated('action') === 'post') {
                Gate::authorize('post', $saved);
                $saved = $post->handle($saved, $userId);
            }
        } catch (AccountingRuleViolated $exception) {
            // A failed post leaves the saved draft; the person fixes it and posts again.
            $draft = $saved ?? $entry;

            return ($draft instanceof JournalEntry ? to_route('accounting.journals.edit', $draft) : back()->withInput())->with('error', $exception->getMessage());
        }

        return to_route('accounting.journals.show', $saved)->with('success', $saved->isDraft() ? __('Draft saved.') : __('Entry :no posted.', ['no' => $saved->entry_no]));
    }

    private function form(?JournalEntry $entry): View
    {
        $accounts = Account::query()->where('is_group', false)->where('is_active', true)->orderBy('code')->get();
        $lines = old('lines', $entry?->lines->map(fn (JournalLine $line): array => [
            'account_id' => $line->account_id, 'debit' => (float) $line->debit > 0 ? $line->debit : '', 'credit' => (float) $line->credit > 0 ? $line->credit : '', 'description' => $line->description,
            'property_id' => $line->property_id, 'department_id' => $line->department_id, 'party_type' => $line->party_type?->value, 'party_id' => $line->party_id,
        ])->all() ?? [
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => '', 'property_id' => '', 'department_id' => '', 'party_type' => '', 'party_id' => ''],
            ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => '', 'property_id' => '', 'department_id' => '', 'party_type' => '', 'party_id' => ''],
        ]);

        return view('accounting::journals.form', [
            'entry' => $entry, 'lines' => $lines,
            'accounts' => $accounts->map(fn (Account $account): array => ['id' => $account->id, 'label' => $account->label()])->values()->all(),
            'properties' => collect(app(PropertyDirectory::class)->all())->map(fn (PropertySummary $property): array => ['id' => $property->id, 'label' => $property->name])->all(),
            'departments' => collect(app(DepartmentDirectory::class)->all())->map(fn (DepartmentSummary $department): array => ['id' => $department->id, 'label' => $department->name])->all(),
            'partyTypes' => collect(PartyType::cases())->map(fn (PartyType $type): array => ['value' => $type->value, 'label' => $type->label()])->all(),
            'currency' => (string) app(Settings::class)->get('accounting.base_currency'),
            'canPost' => auth()->user()?->can('accounting.journal.post') ?? false,
        ]);
    }
}
