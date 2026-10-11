<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a stock movement is (Step 5.3). Each type adds to stock, takes from it, or (a reversal) does the opposite
 * of the movement it reverses.
 */
enum MovementType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ReturnToVendor = 'return_to_vendor';
    case Wastage = 'wastage';
    case Consumption = 'consumption';
    case ProductionIn = 'production_in';
    case ProductionOut = 'production_out';
    case Reversal = 'reversal';

    /** True when the type adds stock, false when it takes stock, null for a reversal (it follows the original). */
    public function adds(): ?bool
    {
        return match ($this) {
            self::Opening, self::Receipt, self::TransferIn, self::AdjustmentIn, self::ProductionIn => true,
            self::Issue, self::TransferOut, self::AdjustmentOut, self::ReturnToVendor, self::Wastage, self::Consumption, self::ProductionOut => false,
            self::Reversal => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('Opening balance'),
            self::Receipt => __('Receipt'),
            self::Issue => __('Issue'),
            self::TransferOut => __('Transfer out'),
            self::TransferIn => __('Transfer in'),
            self::AdjustmentIn => __('Adjustment in'),
            self::AdjustmentOut => __('Adjustment out'),
            self::ReturnToVendor => __('Return to vendor'),
            self::Wastage => __('Wastage'),
            self::Consumption => __('Sale consumption'),
            self::ProductionIn => __('Production in'),
            self::ProductionOut => __('Production out'),
            self::Reversal => __('Reversal'),
        };
    }

    public function color(): string
    {
        return match ($this->adds()) {
            true => 'success',
            false => 'danger',
            null => 'warning',
        };
    }
}
