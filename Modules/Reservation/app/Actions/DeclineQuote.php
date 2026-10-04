<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Exceptions\QuoteNotOpen;
use Modules\Reservation\Models\Quote;

/**
 * The guest turned the quote down.
 */
class DeclineQuote extends Action
{
    /**
     * @throws QuoteNotOpen
     */
    public function handle(Quote $quote): Quote
    {
        if (! $quote->status->isOpen()) {
            throw new QuoteNotOpen(__('Quote :code is :status.', ['code' => $quote->code, 'status' => strtolower($quote->status->label())]));
        }

        $quote->forceFill(['status' => QuoteStatus::Declined, 'declined_at' => now()])->save();

        return $quote;
    }
}
