<?php

namespace Modules\Accounting\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Accounting\Console\BackpostCommand;
use Modules\Accounting\Console\SeedChartCommand;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Models\Voucher;
use Modules\Accounting\Policies\AccountPolicy;
use Modules\Accounting\Policies\BankingPolicy;
use Modules\Accounting\Policies\JournalEntryPolicy;
use Modules\Accounting\Policies\VoucherPolicy;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AccountingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Accounting';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'accounting';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SeedChartCommand::class,
        BackpostCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'account' => Account::class,
            'fiscal_year' => FiscalYear::class,
            'fiscal_period' => FiscalPeriod::class,
            'journal_entry' => JournalEntry::class,
            'journal_line' => JournalLine::class,
            'voucher' => Voucher::class,
            'bank_account' => BankAccount::class,
        ]);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(Voucher::class, VoucherPolicy::class);
        Gate::policy(BankAccount::class, BankingPolicy::class);
        $this->registerDocumentTypes();

        // Entry numbers come from Core's standard `journal` document type (JV-2026-00001).

        $accountant = DefaultRole::Accountant;
        $gm = DefaultRole::GeneralManager;

        $this->app->make(PermissionRegistry::class)->register('Accounting', [
            new PermissionDefinition('accounting.account.view', 'View the chart of accounts', [$gm, $accountant]),
            new PermissionDefinition('accounting.account.manage', 'Add and change ledger accounts', [$accountant]),
            new PermissionDefinition('accounting.period.view', 'View fiscal years and periods', [$gm, $accountant]),
            new PermissionDefinition('accounting.period.manage', 'Create fiscal years, close and lock periods', [$accountant]),
            new PermissionDefinition('accounting.period.reopen', 'Reopen closed and locked periods', [$gm]),
            new PermissionDefinition('accounting.journal.view', 'View journal entries', [$gm, $accountant]),
            new PermissionDefinition('accounting.journal.create', 'Write manual journal entries (drafts)', [$accountant]),
            new PermissionDefinition('accounting.journal.post', 'Post journal entries', [$accountant]),
            new PermissionDefinition('accounting.journal.reverse', 'Reverse posted journal entries', [$accountant]),
            new PermissionDefinition('accounting.bank.view', 'View bank accounts, transfers, cheques and reconciliations', [$gm, $accountant]),
            new PermissionDefinition('accounting.bank.manage', 'Keep bank accounts, transfers, cheques and import statements', [$accountant]),
            new PermissionDefinition('accounting.bank.reconcile', 'Match bank statement lines and complete reconciliations', [$accountant]),
            new PermissionDefinition('accounting.voucher.view', 'View income and expense vouchers', [$gm, $accountant]),
            new PermissionDefinition('accounting.voucher.create', 'Record income and expense vouchers', [$accountant]),
            new PermissionDefinition('accounting.voucher.void', 'Void posted vouchers', [$accountant]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('accounting', 'Accounting', 'bi-journal-bookmark', order: 190);
        $menu->add(new MenuItem('accounting.journals', 'Journal entries', route: 'accounting.journals.index', parent: 'accounting', order: 10,
            permission: 'accounting.journal.view', module: 'accounting', active: 'accounting.journals.*'));
        $menu->add(new MenuItem('accounting.vouchers', 'Vouchers', route: 'accounting.vouchers.index', parent: 'accounting', order: 15,
            permission: 'accounting.voucher.view', module: 'accounting', active: 'accounting.vouchers.*'));
        $menu->add(new MenuItem('accounting.accounts', 'Chart of accounts', route: 'accounting.accounts.index', parent: 'accounting', order: 20,
            permission: 'accounting.account.view', module: 'accounting', active: 'accounting.accounts.*'));
        $menu->add(new MenuItem('accounting.mappings', 'Account mapping', route: 'accounting.mappings.index', parent: 'accounting', order: 25,
            permission: 'accounting.account.view', module: 'accounting', active: 'accounting.mappings.*'));
        $menu->add(new MenuItem('accounting.banks', 'Bank accounts', route: 'accounting.banks.index', parent: 'accounting', order: 40,
            permission: 'accounting.bank.view', module: 'accounting', active: 'accounting.banks.*'));
        $menu->add(new MenuItem('accounting.transfers', 'Transfers', route: 'accounting.transfers.index', parent: 'accounting', order: 41,
            permission: 'accounting.bank.view', module: 'accounting', active: 'accounting.transfers.*'));
        $menu->add(new MenuItem('accounting.cheques', 'Cheques', route: 'accounting.cheques.index', parent: 'accounting', order: 42,
            permission: 'accounting.bank.view', module: 'accounting', active: 'accounting.cheques.*'));
        $menu->add(new MenuItem('accounting.reconciliation', 'Reconciliation', route: 'accounting.reconciliation.index', parent: 'accounting', order: 43,
            permission: 'accounting.bank.view', module: 'accounting', active: 'accounting.reconciliation.*'));
        $menu->add(new MenuItem('accounting.periods', 'Fiscal periods', route: 'accounting.periods.index', parent: 'accounting', order: 30,
            permission: 'accounting.period.view', module: 'accounting', active: 'accounting.periods.*'));

        $settings = $this->app->make(Settings::class);
        $settings->define(new SettingDefinition('accounting.base_currency', 'Base currency of the books', SettingType::Text, 'BDT', SettingScope::Tenant, 'Accounting',
            help: 'Journal entries are kept in this currency (ISO code). Multi-currency comes later.', rules: ['size:3']));
        $settings->define(new SettingDefinition('accounting.reconcile_date_window', 'Days a bank line may differ from its ledger line', SettingType::Integer, 5, SettingScope::Tenant, 'Accounting',
            help: 'Bank reconciliation suggests a match when the amounts agree and the dates are this close.', rules: ['min:0', 'max:60']));
        $settings->define(new SettingDefinition('accounting.fiscal_year_start_month', 'Fiscal year starts in', SettingType::Select, '01', SettingScope::Tenant, 'Accounting',
            help: 'The month a new fiscal year starts with (calendar year: January).', options: collect(range(1, 12))->mapWithKeys(fn (int $month): array => [sprintf('%02d', $month) => date('F', mktime(0, 0, 0, $month, 1))])->all()));
    }

    /**
     * Income and expense vouchers have their own numbers (IV-2026-00001, EV-2026-00001).
     */
    private function registerDocumentTypes(): void
    {
        $numbers = $this->app->make(DocumentNumbers::class);
        $numbers->register(new DocumentType('income_voucher', 'Income voucher', 'IV'));
        $numbers->register(new DocumentType('expense_voucher', 'Expense voucher', 'EV'));
        $numbers->register(new DocumentType('fund_transfer', 'Fund transfer', 'TR'));
    }
}
