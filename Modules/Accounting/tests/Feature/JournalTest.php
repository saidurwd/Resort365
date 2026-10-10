<?php

/*
| Accounting (Step 4.1). "Done when": a manual journal posts; an unbalanced one is refused; posting into a
| closed period is refused; a posted entry can only be reversed.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\Events\TenantCreated;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\ChangePeriodStatus;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\DeleteAccount;
use Modules\Accounting\Actions\DiscardJournalDraft;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\ReverseJournalEntry;
use Modules\Accounting\Actions\SaveAccount;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle('2026-01-01');
    });
});

function acct(string $key): int
{
    return booking(fn (): int => Account::query()->where('system_key', $key)->value('id'));
}

/**
 * @return list<array<string, mixed>>
 */
function jvLines(string $amount = '100.00', ?string $credit = null): array
{
    return [['account_id' => acct('cash_on_hand'), 'debit' => $amount], ['account_id' => acct('owners_capital'), 'credit' => $credit ?? $amount]];
}

/**
 * @param  list<array<string, mixed>>|null  $lines
 */
function jvDraft(string $date = '2026-03-10', ?array $lines = null): JournalEntry
{
    return booking(fn (): JournalEntry => app(SaveJournalDraft::class)->handle(null, ['entry_date' => $date, 'description' => 'Test entry'], $lines ?? jvLines()));
}

function jvPost(JournalEntry $entry): JournalEntry
{
    return booking(fn (): JournalEntry => app(PostJournalEntry::class)->handle($entry));
}

it('posts a manual journal with a gap-free number', function (): void {
    $first = jvPost(jvDraft());
    $second = jvPost(jvDraft('2026-03-11'));

    expect($first->status)->toBe(JournalStatus::Posted)->and($first->entry_no)->toStartWith('JV-')
        ->and($second->entry_no)->not->toBe($first->entry_no)->and(booking(fn () => $first->period->name))->toBe('March 2026');
});

it('refuses an unbalanced entry', function (): void {
    $draft = jvDraft(lines: jvLines('100.00', '90.00'));

    expect(fn () => jvPost($draft))->toThrow(AccountingRuleViolated::class);
    expect(booking(fn () => $draft->fresh()->status))->toBe(JournalStatus::Draft);
});

it('refuses posting into a closed period', function (): void {
    booking(fn () => app(ChangePeriodStatus::class)->handle(FiscalPeriod::query()->where('number', 1)->sole(), PeriodStatus::Closed));
    $draft = jvDraft('2026-01-15');

    expect(fn () => jvPost($draft))->toThrow(AccountingRuleViolated::class);
});

it('refuses a date outside every fiscal year', function (): void {
    expect(fn () => jvPost(jvDraft('2030-01-01')))->toThrow(AccountingRuleViolated::class);
});

it('refuses posting to a group account', function (): void {
    $group = booking(fn (): int => Account::query()->where('is_group', true)->value('id'));
    $draft = jvDraft(lines: [['account_id' => $group, 'debit' => '10.00'], ['account_id' => acct('owners_capital'), 'credit' => '10.00']]);

    expect(fn () => jvPost($draft))->toThrow(AccountingRuleViolated::class);
});

it('keeps a posted entry and its lines unchangeable', function (): void {
    $entry = jvPost(jvDraft());

    expect(fn () => booking(fn () => $entry->fresh()->update(['description' => 'Changed'])))->toThrow(AccountingRuleViolated::class)
        ->and(fn () => booking(fn () => $entry->fresh()->delete()))->toThrow(AccountingRuleViolated::class)
        ->and(fn () => booking(fn () => JournalLine::query()->where('journal_entry_id', $entry->id)->first()->update(['debit' => '1.00'])))->toThrow(AccountingRuleViolated::class)
        ->and(fn () => booking(fn () => app(DiscardJournalDraft::class)->handle($entry)))->toThrow(AccountingRuleViolated::class);
});

it('reverses a posted entry once, with swapped lines', function (): void {
    $entry = jvPost(jvDraft());
    $reversal = booking(fn (): JournalEntry => app(ReverseJournalEntry::class)->handle($entry, '2026-03-20', null, 'Mistake'));

    expect($reversal->status)->toBe(JournalStatus::Posted)->and($reversal->reverses_id)->toBe($entry->id)
        ->and(booking(fn () => $entry->fresh()->status))->toBe(JournalStatus::Reversed)
        ->and(booking(fn () => (string) $reversal->lines()->where('account_id', acct('cash_on_hand'))->value('credit')))->toBe('100.00');
    expect(fn () => booking(fn () => app(ReverseJournalEntry::class)->handle($entry->fresh())))->toThrow(AccountingRuleViolated::class);
    expect(fn () => booking(fn () => app(ReverseJournalEntry::class)->handle($reversal)))->toThrow(AccountingRuleViolated::class);
});

it('refuses to reverse a draft', function (): void {
    expect(fn () => booking(fn () => app(ReverseJournalEntry::class)->handle(jvDraft())))->toThrow(AccountingRuleViolated::class);
});

it('discards a draft', function (): void {
    $draft = jvDraft();
    booking(fn () => app(DiscardJournalDraft::class)->handle($draft));

    expect(booking(fn () => JournalEntry::query()->count()))->toBe(0);
});

it('checks dimensions against the tenant', function (): void {
    $lines = jvLines();
    $lines[0]['property_id'] = 999999;

    expect(fn () => jvDraft(lines: $lines))->toThrow(AccountingRuleViolated::class);

    $lines[0]['property_id'] = bookingIds()['property'];
    expect(jvDraft(lines: $lines)->lines->first()->property_id)->toBe(bookingIds()['property']);
});

it('seeds the chart once and for a new tenant', function (): void {
    $count = booking(fn (): int => Account::query()->count());
    booking(fn () => app(SeedChartOfAccounts::class)->handle());
    expect(booking(fn (): int => Account::query()->count()))->toBe($count);

    $other = Tenant::factory()->create(['slug' => 'other']);
    event(new TenantCreated($other));
    expect(booking(fn (): int => Account::query()->count(), 'other'))->toBe($count);
});

it('keeps account codes unique and refuses deleting a used account', function (): void {
    $cash = acct('cash_on_hand');
    jvPost(jvDraft());
    $code = booking(fn () => Account::query()->findOrFail($cash)->code);

    expect(fn () => booking(fn () => app(SaveAccount::class)->handle(null, ['code' => $code, 'name' => 'Duplicate', 'type' => 'asset', 'parent_id' => null, 'is_group' => false, 'is_active' => true])))
        ->toThrow(QueryException::class);
    expect(fn () => booking(fn () => app(DeleteAccount::class)->handle(Account::query()->findOrFail($cash))))->toThrow(AccountingRuleViolated::class);
});

it('closes periods in order and only without drafts', function (): void {
    jvDraft('2026-01-10');
    $change = fn (int $number, PeriodStatus $to) => booking(fn () => app(ChangePeriodStatus::class)->handle(FiscalPeriod::query()->where('number', $number)->sole(), $to));

    expect(fn () => $change(2, PeriodStatus::Closed))->toThrow(AccountingRuleViolated::class)
        ->and(fn () => $change(1, PeriodStatus::Closed))->toThrow(AccountingRuleViolated::class);
});

it('reopens a closed period only with permission and a reason', function (): void {
    $period = fn () => FiscalPeriod::query()->where('number', 1)->sole();
    booking(fn () => app(ChangePeriodStatus::class)->handle($period(), PeriodStatus::Closed));

    expect(fn () => booking(fn () => app(ChangePeriodStatus::class)->handle($period(), PeriodStatus::Open)))->toThrow(AccountingRuleViolated::class)
        ->and(fn () => booking(fn () => app(ChangePeriodStatus::class)->handle($period(), PeriodStatus::Open, null, true)))->toThrow(AccountingRuleViolated::class);

    booking(fn () => app(ChangePeriodStatus::class)->handle($period(), PeriodStatus::Open, null, true, 'Missed invoice'));
    expect(booking(fn () => $period()->status))->toBe(PeriodStatus::Open);
});

it('lets an accountant post through the screens and refuses a waiter', function (): void {
    staffUser(DefaultRole::Accountant);
    $payload = ['entry_date' => '2026-03-10', 'description' => 'Screen entry', 'action' => 'post', 'lines' => jvLines()];

    post(tenantUrl('sunrise', '/accounting/journals'), $payload)->assertRedirect();
    expect(booking(fn () => JournalEntry::query()->where('description', 'Screen entry')->sole()->status))->toBe(JournalStatus::Posted);

    get(tenantUrl('sunrise', '/accounting/journals'))->assertOk();
    get(tenantUrl('sunrise', '/accounting/accounts'))->assertOk();
    get(tenantUrl('sunrise', '/accounting/periods'))->assertOk();
    get(tenantUrl('sunrise', '/accounting/journals/new'))->assertOk()->assertSeeHtml('data-journal-form');

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/accounting/journals'))->assertForbidden();
    post(tenantUrl('sunrise', '/accounting/journals'), $payload)->assertForbidden();
});

it('shows an entry and its actions', function (): void {
    $draft = jvDraft();
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', '/accounting/journals/'.$draft->id))->assertOk()->assertSeeHtml('data-post')->assertSeeHtml('data-entry-lines');
    $posted = jvPost($draft);
    get(tenantUrl('sunrise', '/accounting/journals/'.$posted->id))->assertOk()->assertSeeHtml('data-reverse')->assertDontSeeHtml('data-post');
    get(tenantUrl('sunrise', '/accounting/journals/'.$draft->id.'/edit'))->assertRedirect();
});

it('refuses an unbalanced entry on the screen and keeps the draft', function (): void {
    staffUser(DefaultRole::Accountant);
    $payload = ['entry_date' => '2026-03-10', 'description' => 'Bad entry', 'action' => 'post', 'lines' => jvLines('100.00', '90.00')];

    post(tenantUrl('sunrise', '/accounting/journals'), $payload)->assertRedirect();
    expect(booking(fn () => JournalEntry::query()->where('description', 'Bad entry')->value('status')))->not->toBe(JournalStatus::Posted);
});
