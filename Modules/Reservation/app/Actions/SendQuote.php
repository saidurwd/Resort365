<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Enums\GuestEmail;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\QuoteNotOpen;
use Modules\Reservation\Jobs\SendGuestEmail;
use Modules\Reservation\Models\Quote;

/**
 * Emails a quote to its guest (queued, with the quotation PDF) and marks it sent. It can be sent
 * again while it is open.
 */
class SendQuote extends Action
{
    public function __construct(private readonly GuestLookup $guests) {}

    /**
     * @throws BookingNotPossible when the guest has no email address
     * @throws QuoteNotOpen
     */
    public function handle(Quote $quote): Quote
    {
        if (! $quote->currentStatus()->isOpen()) {
            throw new QuoteNotOpen(__('Quote :code is :status.', ['code' => $quote->code, 'status' => strtolower($quote->currentStatus()->label())]));
        }

        if (($this->guests->find($quote->guest_id)->email ?? '') === '') {
            throw new BookingNotPossible(__('The guest has no email address. Add one to the guest profile first.'));
        }

        $quote->forceFill(['status' => QuoteStatus::Sent, 'sent_at' => now()])->save();
        SendGuestEmail::dispatch(GuestEmail::Quote, $quote->id);

        return $quote;
    }
}
