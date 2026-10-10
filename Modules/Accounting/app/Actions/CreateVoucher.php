<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Accounting\DTOs\VoucherData;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Enums\PostingKey;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Voucher;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalWriter;
use Modules\Core\Contracts\DocumentNumbers;

/**
 * Records a quick income or expense voucher and posts it at once (ARCHITECTURE §5.14): an income voucher
 * debits the cash or bank account and credits the income account; an expense voucher debits the expense
 * account (and input VAT) and credits the cash or bank account. A closed period refuses the voucher.
 * TODO(step-5.1): large expense vouchers wait for approval once the approval engine exists.
 */
class CreateVoucher extends Action
{
    public function __construct(
        private readonly JournalWriter $writer,
        private readonly PostJournalEntry $post,
        private readonly AccountResolver $accounts,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(VoucherData $data, ?int $userId = null): Voucher
    {
        $amount = BigDecimal::of($data->amount)->toScale(2);
        $tax = BigDecimal::of($data->taxAmount)->toScale(2);

        if (! $amount->isPositive()) {
            throw new AccountingRuleViolated(__('The amount must be more than zero.'));
        }

        if ($tax->isNegative() || ($data->type === VoucherType::Income && ! $tax->isZero()) || $tax->isGreaterThanOrEqualTo($amount)) {
            throw new AccountingRuleViolated(__('The VAT must be less than the amount, and only an expense carries it.'));
        }

        $account = Account::query()->find($data->accountId);
        $cash = Account::query()->find($data->cashAccountId);

        if (! $account instanceof Account || $account->type !== $data->type->accountType() || $account->is_group || ! $account->is_active) {
            throw new AccountingRuleViolated(__('Choose an active :type account.', ['type' => mb_strtolower($data->type->accountType()->label())]));
        }

        if (! $cash instanceof Account || $cash->type !== AccountType::Asset || $cash->is_group || ! $cash->is_active) {
            throw new AccountingRuleViolated(__('Choose an active cash or bank account.'));
        }

        return $this->transaction(function () use ($data, $amount, $tax, $userId): Voucher {
            $voucher = Voucher::query()->create([
                'property_id' => $data->propertyId, 'voucher_no' => $this->numbers->next($data->type->documentType(), $data->propertyId, CarbonImmutable::parse($data->date)),
                'type' => $data->type, 'voucher_date' => $data->date, 'account_id' => $data->accountId, 'cash_account_id' => $data->cashAccountId, 'department_id' => $data->departmentId,
                'payee' => $data->payee, 'description' => $data->description, 'reference' => $data->reference, 'cheque_no' => $data->chequeNo, 'cheque_date' => $data->chequeNo !== null ? ($data->chequeDate ?? $data->date) : null, 'cheque_status' => $data->chequeNo !== null ? ChequeStatus::Pending : null, 'amount' => (string) $amount, 'tax_amount' => (string) $tax,
                'status' => VoucherStatus::Posted, 'created_by' => $userId,
            ]);

            $side = fn (int $accountId, string $debit, ?int $department = null): array => ['account_id' => $accountId, 'debit' => $debit, 'property_id' => $data->propertyId, 'department_id' => $department];
            $lines = $data->type === VoucherType::Income
                ? [$side($data->cashAccountId, (string) $amount), $side($data->accountId, '-'.$amount, $data->departmentId)]
                : array_values(array_filter([
                    $side($data->accountId, (string) $amount->minus($tax), $data->departmentId),
                    $tax->isZero() ? null : $side($this->accounts->fixed(PostingKey::InputVat), (string) $tax),
                    $side($data->cashAccountId, '-'.$amount),
                ]));

            $entry = $this->writer->saveDraft(null, [
                'entry_date' => $data->date, 'description' => mb_substr($voucher->voucher_no.' · '.$data->description, 0, 300), 'reference' => $data->reference ?? $data->chequeNo ?? $voucher->voucher_no,
                'source_type' => 'voucher', 'source_id' => $voucher->id, 'source_event' => 'posted',
            ], array_map(fn (array $line): array => [
                'account_id' => $line['account_id'], 'property_id' => $line['property_id'], 'department_id' => $line['department_id'],
                'debit' => str_starts_with($line['debit'], '-') ? null : $line['debit'], 'credit' => str_starts_with($line['debit'], '-') ? ltrim($line['debit'], '-') : null,
            ], $lines), $userId);
            $voucher->forceFill(['journal_entry_id' => $this->post->handle($entry, $userId)->id])->save();

            return $voucher;
        }, attempts: 3);
    }
}
