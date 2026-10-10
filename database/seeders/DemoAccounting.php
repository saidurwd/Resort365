<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Accounting\Actions\AcceptSuggestions;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\ChangePeriodStatus;
use Modules\Accounting\Actions\CompleteReconciliation;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\CreateTransfer;
use Modules\Accounting\Actions\CreateVoucher;
use Modules\Accounting\Actions\ImportStatement;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\ReverseJournalEntry;
use Modules\Accounting\Actions\SaveAccount;
use Modules\Accounting\Actions\SaveAccountMappings;
use Modules\Accounting\Actions\SaveBankAccount;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\DTOs\TransferData;
use Modules\Accounting\DTOs\VoucherData;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\BankLedger;
use Modules\Restaurant\Contracts\RestaurantFacts;

/**
 * Accounting demo (Step 4.1): the chart of accounts, the current fiscal year with the earlier months closed,
 * an opening-balance entry, an accrual with its reversal and one draft; then the earlier operations are
 * posted to the ledger (Step 4.2).
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

            // Step 4.3: each restaurant outlet books its food and beverage sales to its own revenue accounts.
            self::outletRevenueAccounts();

            // Step 4.2: the demo guest journey so far (deposits, payments, settlements, closed days of charges) reaches the ledger.
            app(BackpostHistory::class)->handle();

            // Step 4.3: an event hall rental received into the bank, and fuel bought for cash (VAT included).
            $voucher = fn (VoucherType $type, string $account, string $cash, string $amount, string $description, string $payee, string $tax = '0.00') => app(CreateVoucher::class)->handle(
                new VoucherData($type, $propertyId, $today->toDateString(), $byCode($account), $byCode($cash), $amount, $description, $tax, payee: $payee),
            );
            $voucher(VoucherType::Income, '4230', '1120', '25000.00', 'Event hall rental', 'Dhaka Bank Ltd');
            $voucher(VoucherType::Expense, '6130', '1110', '8000.00', 'Generator fuel', 'Cox Fuel Station', '1043.48');

            // Step 4.4: bank accounts, a transfer, a cheque still to clear, and the bank's statement reconciled against the books.
            self::banking($propertyId, $today);

            // Months before last month are closed.
            FiscalPeriod::query()->where('fiscal_year_id', $year->id)->where('ends_on', '<', $today->startOfMonth()->subMonth()->startOfMonth()->toDateString())
                ->orderBy('number')->get()
                ->each(fn (FiscalPeriod $period) => app(ChangePeriodStatus::class)->handle($period, PeriodStatus::Closed));
        });
    }

    private static function banking(int $propertyId, CarbonImmutable $today): void
    {
        $code = fn (string $code): int => Account::query()->where('code', $code)->value('id');
        $savings = app(SaveAccount::class)->handle(null, ['code' => '1125', 'name' => 'BRAC Bank savings', 'type' => 'asset', 'parent_id' => $code('1100'), 'is_group' => false]);
        $save = fn (int $account, string $name, string $kind, ?string $bank = null, ?string $number = null): BankAccount => app(SaveBankAccount::class)->handle(null, ['account_id' => $account, 'name' => $name, 'kind' => $kind, 'bank_name' => $bank, 'account_number' => $number]);
        $current = $save($code('1120'), 'City Bank current', 'bank', 'City Bank', '1234567890');
        $reserve = $save($savings->id, 'BRAC Bank savings', 'bank', 'BRAC Bank', '9876543210');
        $save($code('1110'), 'Front desk cash', 'cash');

        // 100,000 moved to savings, and a cheque to a supplier that the bank has not cleared yet.
        app(CreateTransfer::class)->handle(new TransferData($propertyId, $today->toDateString(), $current->id, $reserve->id, '100000.00', 'TT-1042', 'Reserve for the monsoon season'));
        app(CreateVoucher::class)->handle(new VoucherData(VoucherType::Expense, $propertyId, $today->toDateString(), $code('6130'), $code('1120'), '5000.00', 'Office supplies', '0.00', payee: 'Dhaka Stationers', chequeNo: 'CHQ-7701'));

        // The bank's statement shows every posted line of the account except that cheque.
        $rows = ['date,description,reference,withdrawal,deposit'];
        $lines = JournalLine::query()->where('account_id', $current->account_id)->with('entry')->orderBy('journal_entry_id')->orderBy('line_no')->get()
            ->filter(fn (JournalLine $line): bool => $line->entry->reference !== 'CHQ-7701' && $line->entry->status->value !== 'draft');

        foreach ($lines as $line) {
            $rows[] = implode(',', [$line->entry->entry_date->toDateString(), '"'.str_replace('"', '""', $line->entry->description).'"', $line->entry->reference ?? '', (float) $line->credit > 0 ? $line->credit : '', (float) $line->debit > 0 ? $line->debit : '']);
        }

        $closing = bcadd(app(BankLedger::class)->balance($current, $today->toDateString()), '5000.00', 2);
        $statement = app(ImportStatement::class)->handle($current, 'city-bank-'.$today->format('Y-m').'.csv', implode("\n", $rows), $closing)['statement'];
        app(AcceptSuggestions::class)->handle($statement);
        app(CompleteReconciliation::class)->handle($statement);
    }

    private static function outletRevenueAccounts(): void
    {
        $group = Account::query()->where('code', '4200')->value('id');
        $mappings = [];
        foreach (app(RestaurantFacts::class)->outlets() as $index => $outlet) {
            foreach (['food' => 0, 'beverage' => 1] as $class => $offset) {
                $code = (string) (4240 + $index * 20 + $offset * 10);

                if (Account::query()->where('code', $code)->exists()) {
                    continue;
                }

                $account = app(SaveAccount::class)->handle(null, ['code' => $code, 'name' => $outlet['name'].' '.$class.' revenue', 'type' => 'income', 'parent_id' => $group, 'is_group' => false, 'usali_department' => 'fnb']);
                $mappings['outlet:'.$outlet['id'].':'.$class] = $account->id;
            }
        }
        app(SaveAccountMappings::class)->handle($mappings);
    }
}
