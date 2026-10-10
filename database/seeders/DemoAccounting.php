<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Accounting\Actions\ChangePeriodStatus;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\ReverseJournalEntry;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;

/**
 * Accounting demo (Step 4.1): the chart of accounts, the current fiscal year with the earlier months closed,
 * an opening-balance entry, an accrual with its reversal and one draft.
 */
final class DemoAccounting
{
    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            app(SeedChartOfAccounts::class)->handle();

            // Seeding twice keeps the first run's records.
            if (FiscalYear::query()->exists()) {
                return;
            }

            $today = CarbonImmutable::today();
            $year = app(CreateFiscalYear::class)->handle($today->startOfYear()->toDateString());
            $account = fn (string $key): int => Account::query()->where('system_key', $key)->value('id');
            $byCode = fn (string $code): int => Account::query()->where('code', $code)->value('id');
            $save = fn (string $date, string $description, array $lines): JournalEntry => app(SaveJournalDraft::class)->handle(null, ['entry_date' => $date, 'description' => $description], $lines);

            $opening = $save($year->starts_on->toDateString(), 'Opening balances', [
                ['account_id' => $account('cash_on_hand'), 'debit' => '50000.00', 'property_id' => $propertyId],
                ['account_id' => $account('bank'), 'debit' => '2450000.00', 'property_id' => $propertyId],
                ['account_id' => $account('owners_capital'), 'credit' => '2500000.00'],
            ]);
            app(PostJournalEntry::class)->handle($opening);

            // An accrual at the end of last month, reversed on the first day of this one.
            $accrualDate = $today->startOfMonth()->subDay();
            if ($accrualDate->gte($year->starts_on)) {
                $accrual = $save($accrualDate->toDateString(), 'Accrued electricity bill', [
                    ['account_id' => $byCode('6130'), 'debit' => '85000.00', 'property_id' => $propertyId],
                    ['account_id' => $account('accounts_payable'), 'credit' => '85000.00'],
                ]);
                app(ReverseJournalEntry::class)->handle(app(PostJournalEntry::class)->handle($accrual), $today->startOfMonth()->toDateString(), null, 'Accrual reversed');
            }

            $save($today->toDateString(), 'Bank charges to be confirmed', [
                ['account_id' => $account('bank_charges'), 'debit' => '1250.00', 'property_id' => $propertyId],
                ['account_id' => $account('bank'), 'credit' => '1250.00', 'property_id' => $propertyId],
            ]);

            // Months before last month are closed.
            FiscalPeriod::query()->where('fiscal_year_id', $year->id)->where('ends_on', '<', $today->startOfMonth()->subMonth()->startOfMonth()->toDateString())
                ->orderBy('number')->get()
                ->each(fn (FiscalPeriod $period) => app(ChangePeriodStatus::class)->handle($period, PeriodStatus::Closed));
        });
    }
}
