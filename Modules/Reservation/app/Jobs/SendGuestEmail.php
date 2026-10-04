<?php

namespace Modules\Reservation\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Reservation\Enums\GuestEmail;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\GuestEmailComposer;

/**
 * Sends one guest email in the background (ARCHITECTURE §9: emails and PDFs are queued), as the
 * tenant that dispatched it. $subjectId is the reservation id, or the quote id for a quotation.
 * Locally, run a queue worker (`php artisan queue:work` or `composer dev`).
 */
class SendGuestEmail implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, string>  $extra  more placeholders (fee, refund)
     */
    public function __construct(
        public readonly GuestEmail $email,
        public readonly int $subjectId,
        public readonly array $extra = [],
    ) {}

    public function handle(GuestEmailComposer $composer): void
    {
        if ($this->email === GuestEmail::Quote) {
            $quote = Quote::query()->find($this->subjectId);

            if ($quote instanceof Quote) {
                $composer->sendQuote($quote);
            }

            return;
        }

        $reservation = Reservation::query()->find($this->subjectId);

        if ($reservation instanceof Reservation) {
            $composer->sendForReservation($this->email, $reservation, $this->extra);
        }
    }
}
