<?php

namespace Modules\Reports\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The file formats a report is exported in the background (Step 7.1). CSV is downloaded at once and is not one of them.
 */
enum ExportFormat: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Xlsx => __('Excel'),
            self::Pdf => __('PDF'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Xlsx => 'success',
            self::Pdf => 'danger',
        };
    }

    public function mime(): string
    {
        return match ($this) {
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }
}
