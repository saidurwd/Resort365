<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Accounting\DTOs\TransferData;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\FundTransfer;
use Modules\Accounting\Services\JournalWriter;
use Modules\Core\Contracts\DocumentNumbers;

/**
 * Moves money between two of the tenant's cash or bank accounts and posts it at once: Dr the account it
 * goes to, Cr the account it leaves (Step 4.4).
 */
class CreateTransfer extends Action
{
    public function __construct(
        private readonly JournalWriter $writer,
        private readonly PostJournalEntry $post,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(TransferData $data, ?int $userId = null): FundTransfer
    {
        $amount = BigDecimal::of($data->amount)->toScale(2);
        $from = BankAccount::query()->with('account')->find($data->fromBankAccountId);
        $to = BankAccount::query()->with('account')->find($data->toBankAccountId);

        if (! $amount->isPositive()) {
            throw new AccountingRuleViolated(__('The amount must be more than zero.'));
        }

        if (! $from instanceof BankAccount || ! $to instanceof BankAccount || ! $from->is_active || ! $to->is_active) {
            throw new AccountingRuleViolated(__('Choose two active cash or bank accounts.'));
        }

        if ($from->id === $to->id) {
            throw new AccountingRuleViolated(__('Choose two different accounts.'));
        }

        return $this->transaction(function () use ($data, $amount, $from, $to, $userId): FundTransfer {
            $transfer = FundTransfer::query()->create([
                'property_id' => $data->propertyId, 'transfer_no' => $this->numbers->next('fund_transfer', $data->propertyId, CarbonImmutable::parse($data->date)), 'transfer_date' => $data->date,
                'from_bank_account_id' => $from->id, 'to_bank_account_id' => $to->id, 'amount' => (string) $amount, 'reference' => $data->reference, 'notes' => $data->notes,
                'status' => VoucherStatus::Posted, 'created_by' => $userId,
            ]);
            $entry = $this->writer->saveDraft(null, [
                'entry_date' => $data->date, 'description' => __('Transfer :no: :from to :to', ['no' => $transfer->transfer_no, 'from' => $from->name, 'to' => $to->name]),
                'reference' => $data->reference ?? $transfer->transfer_no, 'source_type' => 'fund_transfer', 'source_id' => $transfer->id, 'source_event' => 'posted',
            ], [
                ['account_id' => $to->account_id, 'debit' => (string) $amount, 'property_id' => $data->propertyId],
                ['account_id' => $from->account_id, 'credit' => (string) $amount, 'property_id' => $data->propertyId],
            ], $userId);
            $transfer->forceFill(['journal_entry_id' => $this->post->handle($entry, $userId)->id])->save();

            return $transfer;
        }, attempts: 3);
    }
}
