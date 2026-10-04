<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Modules\Billing\Contracts\DailyTakings;
use Modules\Billing\DTOs\DailyTakingsSummary;
use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Payment;

class DailyTakingsService implements DailyTakings
{
    public function forDate(int $propertyId, string $date): DailyTakingsSummary
    {
        $lines = FolioLine::query()->with('chargeCode')->where('property_id', $propertyId)->where('posting_date', $date)->where('is_voided', false)
            ->whereIn('line_type', [FolioLineType::Charge->value, FolioLineType::Adjustment->value])->get();
        $zero = BigDecimal::zero();
        $charges = [];
        [$net, $tax, $meals] = [$zero, $zero, $zero];

        foreach ($lines as $line) {
            $category = $line->chargeCode?->category;
            $key = $category instanceof ChargeCategory ? $category->value : 'other';
            $charges[$key] ??= ['label' => $category instanceof ChargeCategory ? $category->label() : __('Adjustments'), 'net' => $zero, 'tax' => $zero];
            $charges[$key]['net'] = $charges[$key]['net']->plus($line->amount);
            $charges[$key]['tax'] = $charges[$key]['tax']->plus($line->tax_amount);
            [$net, $tax, $meals] = [$net->plus($line->amount), $tax->plus($line->tax_amount), $meals->plus($line->meal_amount)];
        }

        $payments = Payment::query()->where('property_id', $propertyId)->where('business_date', $date)->where('status', PaymentStatus::Succeeded->value)->get();
        $byMethod = [];
        [$received, $refunded, $security] = [$zero, $zero, $zero];

        foreach ($payments as $payment) {
            if ($payment->payment_type === PaymentType::SecurityDeposit) {
                $security = $security->plus($payment->amount);

                continue;
            }

            $method = $payment->method;
            $byMethod[$method->value] ??= ['label' => $method->label(), 'received' => $zero, 'refunded' => $zero];
            $refund = $payment->payment_type === PaymentType::Refund;
            $byMethod[$method->value][$refund ? 'refunded' : 'received'] = $byMethod[$method->value][$refund ? 'refunded' : 'received']->plus($payment->amount);
            $refund ? $refunded = $refunded->plus($payment->amount) : $received = $received->plus($payment->amount);
        }

        $money = fn (BigDecimal $amount): string => (string) $amount->toScale(2);
        uksort($byMethod, fn (string $a, string $b): int => array_search($a, PaymentMethod::values(), true) <=> array_search($b, PaymentMethod::values(), true));

        return new DailyTakingsSummary(
            date: $date,
            charges: array_map(fn (array $row): array => ['label' => $row['label'], 'net' => $money($row['net']), 'tax' => $money($row['tax']), 'total' => $money($row['net']->plus($row['tax']))], $charges),
            chargesTotal: $money($net->plus($tax)),
            taxTotal: $money($tax),
            packageMeals: $money($meals),
            payments: array_map(fn (array $row): array => ['label' => $row['label'], 'received' => $money($row['received']), 'refunded' => $money($row['refunded'])], $byMethod),
            receivedTotal: $money($received),
            refundedTotal: $money($refunded),
            securityDeposits: $money($security),
            openShifts: CashierShift::query()->where('property_id', $propertyId)->where('status', CashierShiftStatus::Open->value)->count(),
        );
    }
}
